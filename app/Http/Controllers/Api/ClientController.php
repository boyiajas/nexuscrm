<?php

namespace App\Http\Controllers\Api;

use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    use HasAuditLogging;
    
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Client::query()->with('departments');

        // Department scoping for non-super admins
        if ($user && $user->role !== 'SUPER_ADMIN') {
            $query->whereHas('departments', function ($q) use ($user) {
                $q->where('departments.id', $user->department_id);
            });
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
            });
        }

        if ($dept = $request->get('department')) {
            if ($dept !== 'All') {
                $query->whereHas('departments', function ($q) use ($dept) {
                    $q->where('departments.name', $dept);
                });
            }
        }

        // Filter by department ID
        if ($deptId = $request->get('department_id')) {
            $query->whereHas('departments', function ($q) use ($deptId) {
                $q->where('departments.id', $deptId);
            });
        }

        return $query->orderBy('name')->paginate(20);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Authorization check
        if (!$user || !in_array($user->role, ['SUPER_ADMIN', 'MANAGER'])) {
            abort(403, 'You are not allowed to manage clients.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:clients,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'tags' => ['nullable', 'array'],
        ]);

        DB::beginTransaction();
        try {
            $client = Client::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'assigned_to_id' => $data['assigned_to_id'] ?? null,
                'tags' => $data['tags'] ?? null,
                'primary_department_id' => $data['department_ids'][0] ?? null,
            ]);

            // Sync departments
            $client->departments()->sync($data['department_ids']);

            DB::commit();

            // Manual audit record with richer context
            $this->audit(
                action: "Created client #{$client->id} ({$client->name})",
                module: 'Clients',
                meta: [
                    'client_id' => $client->id,
                    'payload' => $data,
                    'department_ids' => $data['department_ids'],
                ]
            );

            return response()->json($client->load('departments'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create client: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Client $client)
    {
        $user = Auth::user();
        
        // Authorization check
        if (!$user || !in_array($user->role, ['SUPER_ADMIN', 'MANAGER'])) {
            abort(403, 'You are not allowed to manage clients.');
        }

        // Department-based access control for non-super admins
        if ($user->role !== 'SUPER_ADMIN') {
            $clientDepartments = $client->departments->pluck('id')->all();
            if (!in_array($user->department_id, $clientDepartments)) {
                abort(403, 'You are not allowed to update this client.');
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:clients,email,' . $client->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'tags' => ['nullable', 'array'],
        ]);

        DB::beginTransaction();
        try {
            $originalData = $client->toArray();
            $originalDeptIds = $client->departments->pluck('id')->toArray();
            
            $client->update([
                'name' => $data['name'],
                'email' => $data['email'] ?? $client->email,
                'phone' => $data['phone'] ?? $client->phone,
                'assigned_to_id' => $data['assigned_to_id'] ?? $client->assigned_to_id,
                'tags' => $data['tags'] ?? $client->tags,
            ]);

            // Update departments if provided
            if (isset($data['department_ids'])) {
                $client->departments()->sync($data['department_ids']);
                
                // Update primary department (use first one)
                if (!empty($data['department_ids'])) {
                    $client->primary_department_id = $data['department_ids'][0];
                    $client->save();
                }
            }

            DB::commit();

            // Audit log for update
            $changes = [
                'client_id' => $client->id,
                'changes' => array_diff_assoc($client->fresh()->toArray(), $originalData),
            ];
            
            if (isset($data['department_ids'])) {
                $changes['department_changes'] = [
                    'old' => $originalDeptIds,
                    'new' => $data['department_ids']
                ];
            }

            $this->audit(
                action: "Updated client #{$client->id} ({$client->name})",
                module: 'Clients',
                meta: $changes
            );

            return response()->json($client->load('departments'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update client: ' . $e->getMessage()], 500);
        }
    }

    public function import(Request $request)
    {
        $user = Auth::user();
        
        if (!$user || !in_array($user->role, ['SUPER_ADMIN', 'MANAGER'])) {
            abort(403, 'You are not allowed to import clients.');
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        $header = fgetcsv($handle); // Updated header: name,email,phone,department_ids,tags
        $importCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($header)) {
                    $errors[] = "Row has incorrect number of columns: " . implode(',', $row);
                    continue;
                }

                $data = array_combine($header, $row);

                if (empty($data['name'])) {
                    $errors[] = "Row skipped - missing name: " . implode(',', $row);
                    continue;
                }

                // Parse department IDs (can be comma-separated IDs or department names)
                $departmentIds = [];
                if (!empty($data['department_ids'])) {
                    // If it's numeric IDs (comma-separated)
                    if (preg_match('/^\d+(,\d+)*$/', $data['department_ids'])) {
                        $departmentIds = array_map('intval', explode(',', $data['department_ids']));
                    } 
                    // If it's department names (comma-separated)
                    else {
                        $deptNames = array_map('trim', explode(',', $data['department_ids']));
                        foreach ($deptNames as $deptName) {
                            $dept = \App\Models\Department::where('name', $deptName)->first();
                            if ($dept) {
                                $departmentIds[] = $dept->id;
                            }
                        }
                    }
                }

                // For backward compatibility, also check old 'department' field
                if (empty($departmentIds) && !empty($data['department'])) {
                    $dept = \App\Models\Department::where('name', $data['department'])->first();
                    if ($dept) {
                        $departmentIds = [$dept->id];
                    }
                }

                // Create or update client
                $clientData = [
                    'name' => $data['name'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'tags' => isset($data['tags'])
                        ? array_filter(array_map('trim', explode(',', $data['tags'])))
                        : [],
                ];

                if (empty($data['email'])) {
                    // If no email, create with name + timestamp to avoid unique constraint
                    $client = Client::create(array_merge($clientData, [
                        'email' => 'import_' . time() . '_' . $importCount . '@example.com',
                    ]));
                } else {
                    $client = Client::updateOrCreate(
                        ['email' => $data['email']],
                        $clientData
                    );
                }

                // Sync departments if we found any
                if (!empty($departmentIds)) {
                    $client->departments()->sync($departmentIds);
                    $client->primary_department_id = $departmentIds[0];
                    $client->save();
                }

                $importCount++;
            }

            DB::commit();
            fclose($handle);

            // Audit log for import
            $this->audit(
                action: "Imported {$importCount} clients",
                module: 'Clients',
                meta: [
                    'import_count' => $importCount,
                    'errors' => $errors,
                    'file' => $request->file('file')->getClientOriginalName(),
                ]
            );

            return response()->json([
                'message' => 'Import completed',
                'imported' => $importCount,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            
            $this->audit(
                action: "Client import failed",
                module: 'Clients',
                meta: [
                    'error' => $e->getMessage(),
                    'file' => $request->file('file')->getClientOriginalName(),
                ]
            );

            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    public function export(): StreamedResponse
    {
        $user = Auth::user();
        $query = Client::query()->with('departments');

        // Department scoping for non-super admins
        if ($user && $user->role !== 'SUPER_ADMIN') {
            $query->whereHas('departments', function ($q) use ($user) {
                $q->where('departments.id', $user->department_id);
            });
        }

        $fileName = 'clients_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            // Updated header with department_ids instead of single department
            fputcsv($handle, ['name', 'email', 'phone', 'department_ids', 'department_names', 'tags']);
            
            $query->chunk(200, function ($clients) use ($handle) {
                foreach ($clients as $client) {
                    $deptIds = $client->departments->pluck('id')->toArray();
                    $deptNames = $client->departments->pluck('name')->toArray();
                    
                    fputcsv($handle, [
                        $client->name,
                        $client->email,
                        $client->phone,
                        implode(',', $deptIds), // Comma-separated department IDs
                        implode(',', $deptNames), // Comma-separated department names
                        implode(',', $client->tags ?? []),
                    ]);
                }
            });

            fclose($handle);
        };

        // Audit log for export
        $this->audit(
            action: "Exported clients to CSV",
            module: 'Clients',
            meta: [
                'filename' => $fileName,
                'user_id' => $user->id,
            ]
        );

        return response()->stream($callback, 200, $headers);
    }

    public function destroy(Client $client)
    {
        $user = Auth::user();
        
        if (!$user || !in_array($user->role, ['SUPER_ADMIN', 'MANAGER'])) {
            abort(403, 'You are not allowed to delete clients.');
        }

        // Department-based access control for non-super admins
        if ($user->role !== 'SUPER_ADMIN') {
            $clientDepartments = $client->departments->pluck('id')->all();
            if (!in_array($user->department_id, $clientDepartments)) {
                abort(403, 'You are not allowed to delete this client.');
            }
        }

        $clientId = $client->id;
        $clientName = $client->name;
        
        DB::beginTransaction();
        try {
            // Detach from departments first
            $client->departments()->detach();
            
            // Detach from campaigns
            $client->campaigns()->detach();
            
            // Delete the client
            $client->delete();
            
            DB::commit();

            // Audit log for deletion
            $this->audit(
                action: "Deleted client #{$clientId} ({$clientName})",
                module: 'Clients',
                meta: [
                    'client_id' => $clientId,
                    'client_name' => $clientName,
                ]
            );

            return response()->noContent();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete client: ' . $e->getMessage()], 500);
        }
    }

    // Helper method to get department options for frontend
    public function departmentOptions()
    {
        $user = Auth::user();
        $query = \App\Models\Department::query();

        // Department scoping for non-super admins
        if ($user && $user->role !== 'SUPER_ADMIN' && $user->department_id) {
            $query->where('id', $user->department_id);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }
}