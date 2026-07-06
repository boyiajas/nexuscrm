<?php

namespace App\Http\Controllers\Api;

use App\Concerns\GuardsSensitiveExports;
use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Client;
use App\Models\ExportRequest;
use App\Models\ImportUpload;
use App\Models\User;
use App\Services\MalwareScanService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    use HasAuditLogging, GuardsSensitiveExports;

    public function __construct(private MalwareScanService $malwareScan)
    {
    }
    
    public function index(Request $request)
    {
        $user = Auth::user();
        $this->authorizeView($user);
        $query = Client::query()->with(['departments', 'assignedTo:id,name,bank_id']);

        $this->applyBankScope($query, $user);
        $this->applyPortfolioScope($query, $user);

        // Department scoping for non-system-admin users
        $userDepartmentIds = $user?->resolvedDepartmentIds() ?? [];
        if ($user && !$user->canManageSystemSettings() && !empty($userDepartmentIds)) {
            $query->whereHas('departments', function ($q) use ($userDepartmentIds) {
                $q->whereIn('departments.id', $userDepartmentIds);
            });
        }

        if ($search = $request->get('search', $request->get('q'))) {
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

        if ($user && $user->canAccessAllBanks() && $request->filled('bank_id')) {
            $query->where('bank_id', (int) $request->get('bank_id'));
        }

        $clients = $query->orderBy('name')->paginate(20);

        $clients->getCollection()->transform(function (Client $client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'bank_id' => $client->bank_id,
                'bank_name' => $client->bank_name,
                'assigned_to_id' => $client->assigned_to_id,
                'assigned_to_name' => $client->assignedTo?->name,
                'id_number_masked' => $client->maskedIdNumber(),
                'account_number_masked' => $client->maskedAccountNumber(),
                'whatsapp_opted_out_at' => optional($client->whatsapp_opted_out_at)->toDateTimeString(),
                'whatsapp_opt_out_reason' => $client->whatsapp_opt_out_reason,
                'whatsapp_contact_basis' => $client->whatsapp_contact_basis,
                'whatsapp_contact_basis_details' => $client->whatsapp_contact_basis_details,
                'whatsapp_opted_in_at' => optional($client->whatsapp_opted_in_at)->toDateTimeString(),
                'whatsapp_opt_in_source' => $client->whatsapp_opt_in_source,
                'whatsapp_can_receive' => $client->canReceiveWhatsapp(),
                'whatsapp_compliance_status' => $this->whatsappComplianceStatus($client),
                'tags' => $client->tags ?? [],
                'departments' => $client->departments->map(fn ($dept) => [
                    'id' => $dept->id,
                    'name' => $dept->name,
                ])->values(),
            ];
        });

        return $clients;
    }

    /**
     * Show a single client with departments.
     */
    public function show(Client $client)
    {
        $user = Auth::user();
        $this->authorizeView($user);
        $this->authorizeClientBank($user, $client);
        $this->authorizeClientDepartment($user, $client);
        $this->authorizeClientPortfolio($user, $client);

        $client->load(['departments', 'assignedTo:id,name,bank_id']);
        $this->audit(
            action: "Viewed client #{$client->id} ({$client->name})",
            module: 'Clients',
            meta: [
                'client_id' => $client->id,
                'bank_id' => $client->bank_id,
                'assigned_to_id' => $client->assigned_to_id,
            ]
        );
        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'bank_id' => $client->bank_id,
            'bank_name' => $client->bank_name,
            'assigned_to_id' => $client->assigned_to_id,
            'assigned_to_name' => $client->assignedTo?->name,
            'id_number' => $this->shouldMaskSensitiveFields($user) ? $client->maskedIdNumber() : $client->id_number,
            'account_number' => $this->shouldMaskSensitiveFields($user) ? $client->maskedAccountNumber() : $client->account_number,
            'branch_code' => $client->branch_code,
            'id_number_masked' => $client->maskedIdNumber(),
            'account_number_masked' => $client->maskedAccountNumber(),
            'whatsapp_opted_out_at' => optional($client->whatsapp_opted_out_at)->toDateTimeString(),
            'whatsapp_opt_out_reason' => $client->whatsapp_opt_out_reason,
            'whatsapp_contact_basis' => $client->whatsapp_contact_basis,
            'whatsapp_contact_basis_details' => $client->whatsapp_contact_basis_details,
            'whatsapp_opted_in_at' => optional($client->whatsapp_opted_in_at)->toDateTimeString(),
            'whatsapp_opt_in_source' => $client->whatsapp_opt_in_source,
            'whatsapp_can_receive' => $client->canReceiveWhatsapp(),
            'whatsapp_compliance_status' => $this->whatsappComplianceStatus($client),
            'tags' => $client->tags ?? [],
            'departments' => $client->departments->map(function ($dept) {
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Authorization check
        if (!$user || !$user->canManageOperationalData()) {
            abort(403, 'You are not allowed to manage clients.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'id_number' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'branch_code' => ['nullable', 'string', 'max:255'],
            'whatsapp_contact_basis' => ['nullable', 'string', Rule::in(['opt_in', 'contract', 'legitimate_interest', 'bank_instruction', 'consent_refresh'])],
            'whatsapp_contact_basis_details' => ['nullable', 'string', 'max:5000'],
            'whatsapp_opted_in_at' => ['nullable', 'date'],
            'whatsapp_opt_in_source' => ['nullable', 'string', 'max:255'],
            'whatsapp_opted_out' => ['sometimes', 'boolean'],
            'whatsapp_opt_out_reason' => ['nullable', 'string', 'max:255'],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'tags' => ['nullable', 'array'],
        ]);

        $bankId = $this->resolveRequestedBankId($user, $data['bank_id'] ?? null);
        $request->validate([
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('clients', 'email')->where(fn ($query) => $query->where('bank_id', $bankId)),
            ],
        ]);

        DB::beginTransaction();
        try {
            $client = Client::create([
                'bank_id' => $bankId,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'id_number' => $data['id_number'] ?? null,
                'bank_name' => $this->resolveBankName($bankId, $data['bank_name'] ?? null),
                'account_number' => $data['account_number'] ?? null,
                'branch_code' => $data['branch_code'] ?? null,
                'whatsapp_contact_basis' => $data['whatsapp_contact_basis'] ?? null,
                'whatsapp_contact_basis_details' => $data['whatsapp_contact_basis_details'] ?? null,
                'whatsapp_opted_in_at' => $data['whatsapp_opted_in_at'] ?? null,
                'whatsapp_opt_in_source' => $data['whatsapp_opt_in_source'] ?? null,
                'whatsapp_opted_out_at' => !empty($data['whatsapp_opted_out']) ? now() : null,
                'whatsapp_opt_out_reason' => !empty($data['whatsapp_opted_out']) ? ($data['whatsapp_opt_out_reason'] ?? 'manual') : null,
                'assigned_to_id' => $this->resolveAssignedUserId($user, $bankId, $data['assigned_to_id'] ?? null),
                'tags' => $data['tags'] ?? null,
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
        if (!$user || !$user->canManageOperationalData()) {
            abort(403, 'You are not allowed to manage clients.');
        }

        // Department-based access control for non-super admins
        $this->authorizeClientBank($user, $client, 'update');
        if (!$user->canManageSystemSettings()) {
            $clientDepartments = $client->departments->pluck('id')->all();
            if (empty(array_intersect($user->resolvedDepartmentIds(), $clientDepartments))) {
                abort(403, 'You are not allowed to update this client.');
            }
        }
        $this->authorizeClientPortfolio($user, $client, 'update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'id_number' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'branch_code' => ['nullable', 'string', 'max:255'],
            'whatsapp_contact_basis' => ['nullable', 'string', Rule::in(['opt_in', 'contract', 'legitimate_interest', 'bank_instruction', 'consent_refresh'])],
            'whatsapp_contact_basis_details' => ['nullable', 'string', 'max:5000'],
            'whatsapp_opted_in_at' => ['nullable', 'date'],
            'whatsapp_opt_in_source' => ['nullable', 'string', 'max:255'],
            'whatsapp_opted_out' => ['sometimes', 'boolean'],
            'whatsapp_opt_out_reason' => ['nullable', 'string', 'max:255'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'tags' => ['nullable', 'array'],
        ]);

        $bankId = $this->resolveRequestedBankId($user, $data['bank_id'] ?? $client->bank_id);
        $request->validate([
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('clients', 'email')
                    ->ignore($client->id)
                    ->where(fn ($query) => $query->where('bank_id', $bankId)),
            ],
        ]);

        DB::beginTransaction();
        try {
            $originalData = $client->toArray();
            $originalDeptIds = $client->departments->pluck('id')->toArray();

            $client->update([
                'bank_id' => $bankId,
                'name' => $data['name'],
                'email' => $data['email'] ?? $client->email,
                'phone' => $data['phone'] ?? $client->phone,
                'id_number' => $data['id_number'] ?? $client->id_number,
                'bank_name' => $this->resolveBankName($bankId, $data['bank_name'] ?? $client->bank_name),
                'account_number' => $data['account_number'] ?? $client->account_number,
                'branch_code' => $data['branch_code'] ?? $client->branch_code,
                'whatsapp_contact_basis' => $data['whatsapp_contact_basis'] ?? $client->whatsapp_contact_basis,
                'whatsapp_contact_basis_details' => $data['whatsapp_contact_basis_details'] ?? $client->whatsapp_contact_basis_details,
                'whatsapp_opted_in_at' => $data['whatsapp_opted_in_at'] ?? $client->whatsapp_opted_in_at,
                'whatsapp_opt_in_source' => $data['whatsapp_opt_in_source'] ?? $client->whatsapp_opt_in_source,
                'whatsapp_opted_out_at' => array_key_exists('whatsapp_opted_out', $data)
                    ? (!empty($data['whatsapp_opted_out']) ? ($client->whatsapp_opted_out_at ?: now()) : null)
                    : $client->whatsapp_opted_out_at,
                'whatsapp_opt_out_reason' => array_key_exists('whatsapp_opted_out', $data)
                    ? (!empty($data['whatsapp_opted_out']) ? ($data['whatsapp_opt_out_reason'] ?? $client->whatsapp_opt_out_reason ?? 'manual') : null)
                    : ($data['whatsapp_opt_out_reason'] ?? $client->whatsapp_opt_out_reason),
                'assigned_to_id' => $this->resolveAssignedUserId($user, $bankId, $data['assigned_to_id'] ?? $client->assigned_to_id),
                'tags' => $data['tags'] ?? $client->tags,
            ]);

            // Update departments if provided
            if (isset($data['department_ids'])) {
                $client->departments()->sync($data['department_ids']);
            }

            DB::commit();

            // Audit log for update
            $changes = [
                'client_id' => $client->id,
                // Use Eloquent tracked changes to avoid array diff warnings on array casts
                'changes' => $client->getChanges(),
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
        
        if (!$user || !$user->canManageOperationalData()) {
            abort(403, 'You are not allowed to import clients.');
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
        ]);

        $bankId = $this->resolveRequestedBankId($user, $request->input('bank_id'));
        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $storedPath = $uploadedFile->store('imports/quarantine', 'local');
        $path = Storage::disk('local')->path($storedPath);

        $importUpload = ImportUpload::create([
            'bank_id' => $bankId,
            'user_id' => $user->id,
            'dataset' => 'clients',
            'original_filename' => $originalName,
            'stored_path' => $storedPath,
            'mime_type' => $uploadedFile->getMimeType(),
            'size_bytes' => $uploadedFile->getSize(),
            'file_hash' => @hash_file('sha256', $path) ?: null,
            'import_status' => 'uploaded',
            'scan_enabled' => $this->malwareScan->isEnabled(),
        ]);

        try {
            $this->validateImportFile($path, $originalName);
        } catch (\Throwable $e) {
            $importUpload->forceFill([
                'import_status' => 'rejected_invalid',
                'error_message' => $e->getMessage(),
            ])->save();
            throw $e;
        }

        $scanResult = $this->scanImportedFileIfEnabled($path, $originalName, $bankId, $importUpload);
        $handle = fopen($path, 'r');

        $header = fgetcsv($handle);
        $normalizedHeader = $this->normalizeImportHeader($header);
        $importCount = 0;
        $createdCount = 0;
        $updatedCount = 0;
        $duplicateCount = 0;
        $skippedCount = 0;
        $errors = [];
        $seenEmails = [];
        $seenPhones = [];

        if (empty($normalizedHeader) || !$this->isValidImportHeader($normalizedHeader)) {
            fclose($handle);
            return response()->json([
                'message' => 'Import failed: the CSV header is invalid. Expected at least a name column and only supported client fields.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $rowNumber = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count($row) !== count($normalizedHeader)) {
                    $errors[] = "Row {$rowNumber} has an incorrect number of columns.";
                    $skippedCount++;
                    continue;
                }

                $data = array_combine($normalizedHeader, $row);

                if (empty($data['name'])) {
                    $errors[] = "Row {$rowNumber} skipped: missing client name.";
                    $skippedCount++;
                    continue;
                }

                $normalizedEmail = !empty($data['email']) ? mb_strtolower(trim((string) $data['email'])) : null;
                $normalizedPhone = !empty($data['phone']) ? preg_replace('/\D+/', '', (string) $data['phone']) : null;

                if ($normalizedEmail && isset($seenEmails[$normalizedEmail])) {
                    $errors[] = "Row {$rowNumber} skipped: duplicate email found in import file ({$data['email']}).";
                    $duplicateCount++;
                    continue;
                }

                if ($normalizedPhone && isset($seenPhones[$normalizedPhone])) {
                    $errors[] = "Row {$rowNumber} skipped: duplicate phone found in import file ({$data['phone']}).";
                    $duplicateCount++;
                    continue;
                }

                if ($normalizedEmail) {
                    $seenEmails[$normalizedEmail] = true;
                }

                if ($normalizedPhone) {
                    $seenPhones[$normalizedPhone] = true;
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
                    'bank_id' => $bankId,
                    'name' => $data['name'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'id_number' => $data['id_number'] ?? null,
                    'bank_name' => $this->resolveBankName($bankId, $data['bank_name'] ?? null),
                    'account_number' => $data['account_number'] ?? null,
                    'branch_code' => $data['branch_code'] ?? null,
                    'whatsapp_contact_basis' => $data['whatsapp_contact_basis'] ?? 'bank_instruction',
                    'whatsapp_contact_basis_details' => $data['whatsapp_contact_basis_details'] ?? 'Imported from bank-provided debtor list.',
                    'whatsapp_opted_in_at' => $data['whatsapp_opted_in_at'] ?? null,
                    'whatsapp_opt_in_source' => $data['whatsapp_opt_in_source'] ?? 'bank_import',
                    'assigned_to_id' => $this->resolveAssignedUserId($user, $bankId, null),
                    'tags' => isset($data['tags'])
                        ? array_filter(array_map('trim', explode(',', $data['tags'])))
                        : [],
                ];

                if (empty($data['email'])) {
                    // If no email, create with name + timestamp to avoid unique constraint
                    $client = Client::create(array_merge($clientData, [
                        'email' => 'import_' . time() . '_' . $importCount . '@example.com',
                    ]));
                    $createdCount++;
                } else {
                    $existing = Client::query()
                        ->where('bank_id', $bankId)
                        ->where('email', $data['email'])
                        ->first();

                    $client = Client::updateOrCreate(
                        ['bank_id' => $bankId, 'email' => $data['email']],
                        $clientData
                    );
                    if ($existing) {
                        $updatedCount++;
                    } else {
                        $createdCount++;
                    }
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

            $importUpload->forceFill([
                'import_status' => 'imported',
                'imported_at' => now(),
                'import_summary' => [
                    'imported' => $importCount,
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'duplicates' => $duplicateCount,
                    'skipped' => $skippedCount,
                    'errors' => $errors,
                ],
            ])->save();

            // Audit log for import
            $this->audit(
                action: "Imported {$importCount} clients",
                module: 'Clients',
                meta: [
                    'import_count' => $importCount,
                    'created_count' => $createdCount,
                    'updated_count' => $updatedCount,
                    'duplicate_count' => $duplicateCount,
                    'skipped_count' => $skippedCount,
                    'errors' => $errors,
                    'file' => $originalName,
                    'malware_scan' => $scanResult,
                ]
            );

            return response()->json([
                'message' => 'Import completed',
                'imported' => $importCount,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'duplicates' => $duplicateCount,
                'skipped' => $skippedCount,
                'upload_id' => $importUpload->id,
                'malware_scan' => $scanResult,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);

            $importUpload->forceFill([
                'import_status' => 'import_failed',
                'error_message' => $e->getMessage(),
            ])->save();
            
            $this->audit(
                action: "Client import failed",
                module: 'Clients',
                meta: [
                    'error' => $e->getMessage(),
                    'file' => $originalName,
                    'upload_id' => $importUpload->id,
                ]
            );

            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $this->authorizeManage($user, 'export clients');
        $exportRequest = $this->authorizeSensitiveExport($request, ExportRequest::DATASET_CLIENTS);
        $query = Client::query()->with('departments');

        $this->applyBankScope($query, $user);
        $this->applyPortfolioScope($query, $user);

        if ($user && $user->canAccessAllBanks() && $request->filled('bank_id')) {
            $query->where('bank_id', (int) $request->get('bank_id'));
        }

        // Department scoping for non-system-admin users
        $userDepartmentIds = $user?->resolvedDepartmentIds() ?? [];
        if ($user && !$user->canManageSystemSettings() && !empty($userDepartmentIds)) {
            $query->whereHas('departments', function ($q) use ($userDepartmentIds) {
                $q->whereIn('departments.id', $userDepartmentIds);
            });
        }

        if ($search = $request->get('search', $request->get('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            });
        }

        if ($dept = $request->get('department')) {
            if (!in_array($dept, ['All', 'all', ''], true)) {
                $query->whereHas('departments', function ($q) use ($dept) {
                    $q->where('departments.name', $dept);
                });
            }
        }

        if ($tag = $request->get('tag')) {
            if (!in_array($tag, ['All', 'all', ''], true)) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        $fileName = 'clients_' . now()->format('Ymd_His') . '.csv';
        $bankScope = $user->canAccessAllBanks()
            ? ($request->filled('bank_id') ? optional(Bank::find($request->get('bank_id')))->name ?? 'Selected Bank' : 'All Banks')
            : (optional($user->bank)->name ?? 'Assigned Bank');

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function () use ($query, $user, $bankScope) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Export Type', 'Clients']);
            fputcsv($handle, ['Exported By', $user->name]);
            fputcsv($handle, ['Exported At', now()->toDateTimeString()]);
            fputcsv($handle, ['Bank Scope', $bankScope]);
            fputcsv($handle, ['User Role', $user->role]);
            fputcsv($handle, []);
            
            fputcsv($handle, ['name', 'email', 'phone', 'bank', 'assigned_to', 'department_ids', 'department_names', 'tags']);
            
            $query->with('assignedTo:id,name')->chunk(200, function ($clients) use ($handle) {
                foreach ($clients as $client) {
                    $deptIds = $client->departments->pluck('id')->toArray();
                    $deptNames = $client->departments->pluck('name')->toArray();
                    
                    fputcsv($handle, [
                        $client->name,
                        $client->email,
                        $client->phone,
                        $client->bank_name,
                        $client->assignedTo?->name,
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
                'bank_scope' => $bankScope,
                'export_request_id' => $exportRequest?->id,
            ]
        );

        $this->markSensitiveExportCompleted($exportRequest, $fileName);

        return response()->stream($callback, 200, $headers);
    }

    public function destroy(Client $client)
    {
        $user = Auth::user();
        $this->authorizeManage($user, 'delete clients');

        // Department-based access control for non-super admins
        $this->authorizeClientBank($user, $client, 'delete');
        $this->authorizeClientDepartment($user, $client, 'delete');
        $this->authorizeClientPortfolio($user, $client, 'delete');

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

        // Department scoping for non-system-admin users
        $userDepartmentIds = $user?->resolvedDepartmentIds() ?? [];
        if ($user && !$user->canManageSystemSettings() && !empty($userDepartmentIds)) {
            $query->whereIn('id', $userDepartmentIds);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    protected function authorizeView($user): void
    {
        if (!$user || !$user->canViewOperationalData()) {
            abort(403, 'You are not allowed to access clients.');
        }
    }

    protected function authorizeManage($user, string $action = 'manage clients'): void
    {
        if (!$user || !$user->canManageOperationalData()) {
            abort(403, "You are not allowed to {$action}.");
        }
    }

    protected function authorizeClientDepartment($user, Client $client, string $action = 'view'): void
    {
        if ($user->canManageSystemSettings()) {
            return;
        }

        $clientDepartments = $client->departments->pluck('id')->all();
        if (empty(array_intersect($user->resolvedDepartmentIds(), $clientDepartments))) {
            abort(403, "You are not allowed to {$action} this client.");
        }
    }

    protected function shouldMaskSensitiveFields($user): bool
    {
        return $user?->isReadOnlyRole() ?? false;
    }

    protected function scanImportedFileIfEnabled(string $path, string $originalName, ?int $bankId, ImportUpload $importUpload): array
    {
        if (!$this->malwareScan->isEnabled()) {
            $importUpload->forceFill([
                'scan_status' => 'skipped',
                'scan_message' => 'Malware scanning is disabled in system settings.',
            ])->save();

            return [
                'status' => 'skipped',
                'engine' => null,
                'reason' => 'Malware scanning is disabled in system settings.',
            ];
        }

        try {
            $importUpload->forceFill([
                'import_status' => 'scanning',
            ])->save();

            $result = $this->malwareScan->scanFile($path, $originalName);
            $importUpload->forceFill([
                'scan_status' => $result['status'] ?? 'unknown',
                'scan_engine' => $result['engine'] ?? null,
                'scan_signature' => $result['signature'] ?? null,
                'scan_message' => $result['raw'] ?? null,
                'scanned_at' => now(),
                'import_status' => ($result['status'] ?? null) === 'infected' ? 'rejected_malware' : 'scan_passed',
            ])->save();

            if (($result['status'] ?? null) === 'infected') {
                $this->audit(
                    action: "Rejected client import upload due to malware detection ({$originalName})",
                    module: 'Clients',
                    meta: [
                        'bank_id' => $bankId,
                        'filename' => $originalName,
                        'malware_scan' => $result,
                    ]
                );

                throw new HttpResponseException(response()->json([
                    'message' => 'Import blocked: the uploaded file failed malware scanning.',
                    'malware_scan' => $result,
                ], 422));
            }

            $this->audit(
                action: "Scanned client import upload ({$originalName})",
                module: 'Clients',
                meta: [
                    'bank_id' => $bankId,
                    'filename' => $originalName,
                    'malware_scan' => $result,
                ]
            );

            return $result;
        } catch (\Throwable $e) {
            $importUpload->forceFill([
                'scan_status' => 'error',
                'scan_engine' => 'clamav',
                'scan_message' => $e->getMessage(),
                'scanned_at' => now(),
                'import_status' => 'scanner_error',
                'error_message' => $e->getMessage(),
            ])->save();

            $this->audit(
                action: "Client import malware scan failed ({$originalName})",
                module: 'Clients',
                meta: [
                    'bank_id' => $bankId,
                    'filename' => $originalName,
                    'error' => $e->getMessage(),
                ]
            );

            throw new HttpResponseException(response()->json([
                'message' => 'Import blocked: the malware scanner is enabled but unavailable. Disable scanning in system settings or restore the scanner daemon before importing.',
                'malware_scan' => [
                    'status' => 'error',
                    'engine' => 'clamav',
                    'reason' => $e->getMessage(),
                ],
            ], 503));
        }
    }

    protected function applyBankScope($query, $user): void
    {
        if ($user && !$user->canAccessAllBanks() && $user->resolvedBankId()) {
            $query->where('bank_id', $user->resolvedBankId());
        }
    }

    protected function applyPortfolioScope($query, $user): void
    {
        if ($user && $user->isPortfolioScoped()) {
            $query->where('assigned_to_id', $user->id);
        }
    }

    protected function authorizeClientBank($user, Client $client, string $action = 'view'): void
    {
        if ($user->canAccessAllBanks()) {
            return;
        }

        if ($user->resolvedBankId() && (int) $client->bank_id !== $user->resolvedBankId()) {
            abort(403, "You are not allowed to {$action} this client.");
        }
    }

    protected function authorizeClientPortfolio($user, Client $client, string $action = 'view'): void
    {
        if ($user->isPortfolioScoped() && (int) $client->assigned_to_id !== (int) $user->id) {
            abort(403, "You are not allowed to {$action} this client.");
        }
    }

    protected function resolveRequestedBankId($user, $requestedBankId): ?int
    {
        if (!$user) {
            return null;
        }

        if ($user->canAccessAllBanks()) {
            if (!$requestedBankId) {
                abort(422, 'A bank is required for this action.');
            }

            return (int) $requestedBankId;
        }

        return $user->resolvedBankId();
    }

    protected function resolveAssignedUserId($user, ?int $bankId, $requestedAssignedUserId): ?int
    {
        if ($user && $user->isPortfolioScoped()) {
            return (int) $user->id;
        }

        if (!$requestedAssignedUserId) {
            return null;
        }

        $assignee = User::query()->find($requestedAssignedUserId);
        if (!$assignee) {
            abort(422, 'The selected assignee is invalid.');
        }

        if ($bankId && (int) $assignee->bank_id !== (int) $bankId) {
            abort(422, 'The selected assignee must belong to the same bank as the client.');
        }

        return (int) $requestedAssignedUserId;
    }

    protected function resolveBankName(?int $bankId, ?string $fallbackName): ?string
    {
        if ($bankId) {
            return Bank::query()->whereKey($bankId)->value('name') ?? $fallbackName;
        }

        return $fallbackName;
    }

    protected function validateImportFile(string $path, string $originalName): void
    {
        $sample = file_get_contents($path, false, null, 0, 4096) ?: '';

        if (str_contains($sample, "\0")) {
            abort(422, 'Import failed: binary file content detected. Only plain CSV uploads are allowed.');
        }

        $mime = function_exists('finfo_open')
            ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path)
            : null;

        $allowedMimes = [
            'text/plain',
            'text/csv',
            'application/csv',
            'application/vnd.ms-excel',
            'text/x-csv',
        ];

        if ($mime && !in_array($mime, $allowedMimes, true)) {
            abort(422, "Import failed: unsupported file type ({$mime}) for {$originalName}.");
        }
    }

    protected function normalizeImportHeader($header): array
    {
        return collect($header ?? [])
            ->map(fn ($column) => trim(mb_strtolower((string) $column)))
            ->all();
    }

    protected function isValidImportHeader(array $header): bool
    {
        $allowed = [
            'name',
            'email',
            'phone',
            'department',
            'department_ids',
            'tags',
            'bank_name',
            'id_number',
            'account_number',
            'branch_code',
            'whatsapp_contact_basis',
            'whatsapp_contact_basis_details',
            'whatsapp_opted_in_at',
            'whatsapp_opt_in_source',
        ];

        return in_array('name', $header, true)
            && empty(array_diff($header, $allowed));
    }

    protected function whatsappComplianceStatus(Client $client): string
    {
        if ($client->isWhatsappSuppressed()) {
            return 'Suppressed';
        }

        if (!$client->hasWhatsappLawfulBasis()) {
            return 'Missing Lawful Basis';
        }

        return 'Eligible';
    }
}
