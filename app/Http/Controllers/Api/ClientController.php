<?php

namespace App\Http\Controllers\Api;

use App\Concerns\GuardsSensitiveExports;
use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\ChatSession;
use App\Models\Client;
use App\Models\Department;
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
        $allowedPerPage = [25, 50, 100, 200, 300, 500, 1000];
        $perPage = (int) $request->integer('per_page', 25);
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 25;
        }

        $this->applyBankScope($query, $user);
        $this->applyPortfolioScope($query, $user);

        // Department scoping for non-system-admin users
        $userDepartmentIds = $user?->resolvedDepartmentIds() ?? [];
        if ($user && !$user->canManageSystemSettings() && !empty($userDepartmentIds)) {
            $query->whereHas('departments', function ($q) use ($userDepartmentIds) {
                $q->whereIn('departments.id', $userDepartmentIds);
            });
        }

        if ($search = trim((string) $request->get('search', $request->get('q')))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('first_name', 'like', "%$search%")
                  ->orWhere('surname', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%")
                  ->orWhere('cell_phone', 'like', "%$search%")
                  ->orWhere('id_number', 'like', "%$search%")
                  ->orWhere('account_number', 'like', "%$search%")
                  ->orWhere('easy_pay_number', 'like', "%$search%");
            });
        }

        if ($dept = $request->get('department')) {
            if ($dept !== 'All' && $dept !== '') {
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

        if ($request->filled('bank_id')) {
            $requestedBankId = (int) $request->get('bank_id');
            if ($user->canAccessAllBanks() || in_array($requestedBankId, $user->resolvedBankIds(), true)) {
                $query->where('bank_id', $requestedBankId);
            }
        }

        if ($status = $request->get('status')) {
            if (!in_array($status, ['All', 'all', ''], true)) {
                $query->where('status', $status);
            }
        }

        if ($optIn = $request->get('opt_in')) {
            if (in_array($optIn, ['opted_in', 'yes', 'Opted In'], true)) {
                $query->whereNull('whatsapp_opted_out_at')
                      ->where(function ($q) {
                          $q->whereNull('opt_in')->orWhere('opt_in', '!=', 'no');
                      })
                      ->where(function ($q) {
                          $q->where('opt_in', 'yes')
                            ->orWhereNotNull('whatsapp_opted_in_at')
                            ->orWhere(function ($sub) {
                                $sub->whereNotNull('whatsapp_contact_basis')->where('whatsapp_contact_basis', '!=', '');
                            });
                      });
            } elseif (in_array($optIn, ['opted_out', 'no', 'Opted Out'], true)) {
                $query->where(function ($q) {
                    $q->whereNotNull('whatsapp_opted_out_at')
                      ->orWhere('opt_in', 'no');
                });
            } elseif (in_array($optIn, ['none', 'None'], true)) {
                $query->whereNull('whatsapp_opted_out_at')
                      ->where(function ($q) {
                          $q->whereNull('opt_in')
                            ->orWhereNotIn('opt_in', ['yes', 'no']);
                      })
                      ->whereNull('whatsapp_opted_in_at')
                      ->where(function ($q) {
                          $q->whereNull('whatsapp_contact_basis')->orWhere('whatsapp_contact_basis', '');
                      });
            }
        }

        $batchOptions = (clone $query)
            ->whereNotNull('import_batch_number')
            ->where('import_batch_number', '!=', '')
            ->distinct()
            ->orderByDesc('import_batch_number')
            ->pluck('import_batch_number')
            ->values();

        if ($batch = trim((string) $request->get('import_batch_number'))) {
            if ($batch === 'manual') {
                $query->where(function ($q) {
                    $q->whereNull('import_batch_number')->orWhere('import_batch_number', '');
                });
            } else {
                $query->where('import_batch_number', $batch);
            }
        }

        $clients = $query->orderBy('name')->paginate($perPage);
        $importBatches = $clients->getCollection()
            ->pluck('import_batch_number')
            ->filter()
            ->unique()
            ->values();

        $uploadsByBatch = ImportUpload::query()
            ->with('user:id,name')
            ->whereIn('import_batch_number', $importBatches)
            ->get()
            ->keyBy('import_batch_number');

        $clients->getCollection()->transform(function (Client $client) use ($uploadsByBatch) {
            $upload = $client->import_batch_number ? $uploadsByBatch->get($client->import_batch_number) : null;
            $createdByName = $upload?->user?->name;
            $createdByLabel = $client->import_batch_number
                ? ($createdByName ?: 'Imported / Unknown')
                : 'Manual / Not tracked';

            return array_merge($client->toArray(), [
                'email' => $this->resolveClientDisplayEmail($client),
                'phone' => $this->resolveClientDisplayPhone($client),
                'assigned_to_name' => $client->assignedTo?->name,
                'created_by_name' => $createdByName,
                'created_by_label' => $createdByLabel,
                'id_number_masked' => $client->maskedIdNumber(),
                'account_number_masked' => $client->maskedAccountNumber(),
                'whatsapp_opted_out_at' => optional($client->whatsapp_opted_out_at)->toDateTimeString(),
                'whatsapp_opted_in_at' => optional($client->whatsapp_opted_in_at)->toDateTimeString(),
                'whatsapp_can_receive' => $client->canReceiveWhatsapp(),
                'whatsapp_compliance_status' => $this->whatsappComplianceStatus($client),
                'departments' => $client->departments->map(fn ($dept) => [
                    'id' => $dept->id,
                    'name' => $dept->name,
                ])->values(),
            ]);
        });

        return response()->json(array_merge($clients->toArray(), [
            'batch_options' => $batchOptions,
        ]));
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
        return response()->json(array_merge($client->toArray(), [
            'email' => $this->resolveClientDisplayEmail($client),
            'phone' => $this->resolveClientDisplayPhone($client),
            'assigned_to_name' => $client->assignedTo?->name,
            'id_number' => $this->shouldMaskSensitiveFields($user) ? $client->maskedIdNumber() : $client->id_number,
            'account_number' => $this->shouldMaskSensitiveFields($user) ? $client->maskedAccountNumber() : $client->account_number,
            'id_number_masked' => $client->maskedIdNumber(),
            'account_number_masked' => $client->maskedAccountNumber(),
            'whatsapp_opted_out_at' => optional($client->whatsapp_opted_out_at)->toDateTimeString(),
            'whatsapp_opted_in_at' => optional($client->whatsapp_opted_in_at)->toDateTimeString(),
            'whatsapp_can_receive' => $client->canReceiveWhatsapp(),
            'whatsapp_compliance_status' => $this->whatsappComplianceStatus($client),
            'tags' => $client->tags ?? [],
            'departments' => $client->departments->map(function ($dept) {
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                ];
            })->values(),
        ]));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Authorization check
        if (!$user || !$user->canCreateClients()) {
            abort(403, 'You do not have permission to create clients.');
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'id_number' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'easy_pay_number' => ['nullable', 'string', 'max:255'],
            'outstanding_balance' => ['nullable', 'numeric'],
            'arrears_amount' => ['nullable', 'numeric'],
            'settlement_amount' => ['nullable', 'numeric'],
            'three_months_amount' => ['nullable', 'numeric'],
            'installment_amount' => ['nullable', 'numeric'],
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
                'name' => $data['name'] ?? trim(($data['first_name'] ?? '') . ' ' . ($data['surname'] ?? '')),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'id_number' => $data['id_number'] ?? null,
                'title' => $data['title'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'surname' => $data['surname'] ?? null,
                'easy_pay_number' => $data['easy_pay_number'] ?? null,
                'outstanding_balance' => $data['outstanding_balance'] ?? null,
                'arrears_amount' => $data['arrears_amount'] ?? null,
                'settlement_amount' => $data['settlement_amount'] ?? null,
                'three_months_amount' => $data['three_months_amount'] ?? null,
                'installment_amount' => $data['installment_amount'] ?? null,
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
        if (!$user || !$user->canEditClients()) {
            abort(403, 'You do not have permission to edit clients.');
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
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'id_number' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'easy_pay_number' => ['nullable', 'string', 'max:255'],
            'outstanding_balance' => ['nullable', 'numeric'],
            'arrears_amount' => ['nullable', 'numeric'],
            'settlement_amount' => ['nullable', 'numeric'],
            'three_months_amount' => ['nullable', 'numeric'],
            'installment_amount' => ['nullable', 'numeric'],
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
                'name' => $data['name'] ?? trim(($data['first_name'] ?? $client->first_name) . ' ' . ($data['surname'] ?? $client->surname)),
                'email' => $data['email'] ?? $client->email,
                'phone' => $data['phone'] ?? $client->phone,
                'id_number' => $data['id_number'] ?? $client->id_number,
                'title' => $data['title'] ?? $client->title,
                'first_name' => $data['first_name'] ?? $client->first_name,
                'surname' => $data['surname'] ?? $client->surname,
                'easy_pay_number' => $data['easy_pay_number'] ?? $client->easy_pay_number,
                'outstanding_balance' => array_key_exists('outstanding_balance', $data) ? $data['outstanding_balance'] : $client->outstanding_balance,
                'arrears_amount' => array_key_exists('arrears_amount', $data) ? $data['arrears_amount'] : $client->arrears_amount,
                'settlement_amount' => array_key_exists('settlement_amount', $data) ? $data['settlement_amount'] : $client->settlement_amount,
                'three_months_amount' => array_key_exists('three_months_amount', $data) ? $data['three_months_amount'] : $client->three_months_amount,
                'installment_amount' => array_key_exists('installment_amount', $data) ? $data['installment_amount'] : $client->installment_amount,
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
        $this->extendImportExecutionLimits();
        
        if (!$user || !$user->canImportClients()) {
            abort(403, 'You are not allowed to import clients.');
        }

        $maxFileKb = (int) env('IMPORT_MAX_FILE_SIZE_KB', 131072);
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', "max:{$maxFileKb}"],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ]);

        $bankId = $this->resolveRequestedBankId($user, $request->input('bank_id'));
        $selectedDepartmentIds = $this->resolveImportDepartmentIds($user, $request->input('department_ids', []));
        $importBatchNumber = $this->generateImportBatchNumber();
        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $storedPath = $uploadedFile->store('imports/quarantine', 'local');
        $path = Storage::disk('local')->path($storedPath);

        $importUpload = ImportUpload::create([
            'bank_id' => $bankId,
            'user_id' => $user->id,
            'dataset' => 'clients',
            'original_filename' => $originalName,
            'import_batch_number' => $importBatchNumber,
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
        $rows = $this->readImportRows($path, $originalName);
        $header = array_shift($rows);
        $normalizedHeader = $this->normalizeImportHeader($header);
        $importCount = 0;
        $createdCount = 0;
        $updatedCount = 0;
        $duplicateCount = 0;
        $skippedCount = 0;
        $errors = [];
        $seenEmails = [];
        $seenPhones = [];
        $bankName = $this->resolveBankName($bankId, null);
        $defaultAssignedToId = $this->resolveAssignedUserId($user, $bankId, null);
        $departmentLookup = Department::query()
            ->pluck('id', 'name')
            ->mapWithKeys(function ($id, $name) {
                return [mb_strtolower(trim((string) $name)) => (int) $id];
            })
            ->all();

        if (empty($normalizedHeader) || !$this->isValidImportHeader($normalizedHeader)) {
            return response()->json([
                'message' => 'Import failed: the file header is invalid. Expected at least a name column and only supported client fields.',
            ], 422);
        }

        DB::disableQueryLog();

        $existingAccounts = [];
        $existingEasyPay = [];
        $existingEmails = [];

        if ($bankId) {
            $existingAccounts = Client::query()
                ->where('bank_id', $bankId)
                ->whereNotNull('account_number')
                ->where('account_number', '!=', '')
                ->pluck('id', 'account_number')
                ->toArray();

            $existingEasyPay = Client::query()
                ->where('bank_id', $bankId)
                ->whereNotNull('easy_pay_number')
                ->where('easy_pay_number', '!=', '')
                ->pluck('id', 'easy_pay_number')
                ->toArray();

            $existingEmails = Client::query()
                ->where('bank_id', $bankId)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->pluck('id', 'email')
                ->toArray();
        }

        DB::beginTransaction();
        try {
            $rowNumber = 1;
            foreach ($rows as $row) {
                $rowNumber++;
                if ($rowNumber % 100 === 0) {
                    $this->refreshImportExecutionWindow();
                }

                if (count($row) !== count($normalizedHeader)) {
                    $errors[] = "Row {$rowNumber} has an incorrect number of columns.";
                    $skippedCount++;
                    continue;
                }

                $data = array_combine($normalizedHeader, $row);

                $firstName = $this->cleanImportString($data['name'] ?? null);
                $surname = $this->cleanImportString($data['surname'] ?? null);
                $fullName = trim(implode(' ', array_filter([$firstName, $surname])));
                if ($fullName === '') {
                    $fullName = $firstName;
                }

                if ($fullName === '') {
                    $errors[] = "Row {$rowNumber} skipped: missing client name.";
                    $skippedCount++;
                    continue;
                }

                $emailValue = $this->firstNonEmptyImportValue([
                    $data['email_personal'] ?? null,
                    $data['patient_email_personal'] ?? null,
                    $data['email'] ?? null,
                    $data['email_work'] ?? null,
                    $data['patient_email_work'] ?? null,
                ]);
                $cellPhone = $this->firstNonEmptyImportValue([
                    $data['cell'] ?? null,
                    $data['cell_phone'] ?? null,
                ]);
                $homePhone = $this->firstNonEmptyImportValue([
                    $data['home'] ?? null,
                    $data['home_phone'] ?? null,
                ]);
                $workPhone = $this->firstNonEmptyImportValue([
                    $data['work'] ?? null,
                    $data['work_phone'] ?? null,
                ]);
                $primaryPhone = $this->firstNonEmptyImportValue([
                    $cellPhone,
                    $this->cleanImportString($data['phone'] ?? null),
                    $homePhone,
                    $workPhone,
                ]);

                $normalizedEmail = $emailValue ? mb_strtolower($emailValue) : null;
                $normalizedPhone = $primaryPhone ? preg_replace('/\D+/', '', (string) $primaryPhone) : null;

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
                            $resolvedDeptId = $departmentLookup[mb_strtolower($deptName)] ?? null;
                            if ($resolvedDeptId) {
                                $departmentIds[] = $resolvedDeptId;
                            }
                        }
                    }
                }

                // For backward compatibility, also check old 'department' field
                if (empty($departmentIds) && !empty($data['department'])) {
                    $resolvedDeptId = $departmentLookup[mb_strtolower(trim((string) $data['department']))] ?? null;
                    if ($resolvedDeptId) {
                        $departmentIds = [$resolvedDeptId];
                    }
                }

                $departmentIds = array_values(array_unique(array_merge($selectedDepartmentIds, $departmentIds)));

                // Create or update client
                $clientData = [
                    'bank_id' => $bankId,
                    'name' => $fullName,
                    'title' => $this->cleanImportString($data['title'] ?? null),
                    'initials' => $this->cleanImportString($data['initials'] ?? null),
                    'first_name' => $firstName,
                    'surname' => $surname,
                    'email' => $emailValue,
                    'phone' => $primaryPhone,
                    'cell_phone' => $cellPhone,
                    'home_phone' => $homePhone,
                    'work_phone' => $workPhone,
                    'id_number' => $this->cleanImportString($data['id_number'] ?? null),
                    'bank_name' => $bankName ?? $this->cleanImportString($data['bank_name'] ?? null),
                    'account_number' => $this->cleanImportString($data['account_number'] ?? null),
                    'easy_pay_number' => $this->cleanImportString($data['easy_pay_number'] ?? null),
                    'branch_code' => $this->cleanImportString($data['branch_code'] ?? null),
                    'arrears_amount' => $this->parseImportAmount($data['arrears_amount'] ?? null),
                    'outstanding_balance' => $this->parseImportAmount($data['outstanding_balance'] ?? null),
                    'settlement_amount' => $this->parseImportAmount($data['settlement_amount'] ?? null),
                    'three_months_amount' => $this->parseImportAmount($data['three_months_amount'] ?? $data['3_months'] ?? null),
                    'installment_amount' => $this->parseImportAmount($data['installment_amount'] ?? null),
                    'last_payment_amount' => $this->parseImportAmount($data['last_payment_amount'] ?? null),
                    'total_payment_amount' => $this->parseImportAmount($data['total_payment_amount'] ?? null),
                    'import_batch_number' => $importBatchNumber,
                    'whatsapp_contact_basis' => $data['whatsapp_contact_basis'] ?? 'bank_instruction',
                    'whatsapp_contact_basis_details' => $data['whatsapp_contact_basis_details'] ?? 'Imported from bank-provided debtor list.',
                    'whatsapp_opted_in_at' => $data['whatsapp_opted_in_at'] ?? null,
                    'whatsapp_opt_in_source' => $data['whatsapp_opt_in_source'] ?? 'bank_import',
                    'assigned_to_id' => $defaultAssignedToId,
                    'tags' => isset($data['tags'])
                        ? array_filter(array_map('trim', explode(',', $data['tags'])))
                        : [],
                ];

                $existingId = null;
                if (!empty($clientData['account_number']) && isset($existingAccounts[$clientData['account_number']])) {
                    $existingId = $existingAccounts[$clientData['account_number']];
                } elseif (!empty($clientData['easy_pay_number']) && isset($existingEasyPay[$clientData['easy_pay_number']])) {
                    $existingId = $existingEasyPay[$clientData['easy_pay_number']];
                } elseif (!empty($emailValue) && isset($existingEmails[$emailValue])) {
                    $existingId = $existingEmails[$emailValue];
                }

                if ($existingId) {
                    $existing = Client::find($existingId);
                    if ($existing) {
                        $existing->fill($clientData);
                        $existing->save();
                        $client = $existing;
                        $updatedCount++;
                    } else {
                        $client = Client::create($clientData);
                        $createdCount++;
                    }
                } else {
                    if (empty($clientData['email'])) {
                        $clientData['email'] = 'import_' . time() . '_' . $importCount . '@example.com';
                    }
                    $client = Client::create($clientData);
                    $createdCount++;
                }

                if (!empty($client->account_number)) {
                    $existingAccounts[$client->account_number] = $client->id;
                }
                if (!empty($client->easy_pay_number)) {
                    $existingEasyPay[$client->easy_pay_number] = $client->id;
                }
                if (!empty($client->email)) {
                    $existingEmails[$client->email] = $client->id;
                }

                // Sync departments if we found any
                if (!empty($departmentIds)) {
                    $client->departments()->sync($departmentIds);
                }

                $importCount++;
            }

            DB::commit();

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
                    'import_batch_number' => $importBatchNumber,
                    'department_ids' => $selectedDepartmentIds,
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
                'import_batch_number' => $importBatchNumber,
                'department_ids' => $selectedDepartmentIds,
                'malware_scan' => $scanResult,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

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
                    'import_batch_number' => $importBatchNumber,
                ]
            );

            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $this->authorizeView($user);
        $exportRequest = $this->authorizeSensitiveExport($request, ExportRequest::DATASET_CLIENTS);
        $query = Client::query()->with('departments');

        $this->applyBankScope($query, $user);
        $this->applyPortfolioScope($query, $user);

        if ($request->filled('bank_id')) {
            $requestedBankId = (int) $request->get('bank_id');
            if ($user->canAccessAllBanks() || in_array($requestedBankId, $user->resolvedBankIds(), true)) {
                $query->where('bank_id', $requestedBankId);
            }
        }

        // Department scoping for non-system-admin users
        $userDepartmentIds = $user?->resolvedDepartmentIds() ?? [];
        if ($user && !$user->canManageSystemSettings() && !empty($userDepartmentIds)) {
            $query->whereHas('departments', function ($q) use ($userDepartmentIds) {
                $q->whereIn('departments.id', $userDepartmentIds);
            });
        }

        if ($search = trim((string) $request->get('search', $request->get('q')))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('first_name', 'like', "%$search%")
                    ->orWhere('surname', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('cell_phone', 'like', "%$search%")
                    ->orWhere('id_number', 'like', "%$search%")
                    ->orWhere('account_number', 'like', "%$search%")
                    ->orWhere('easy_pay_number', 'like', "%$search%");
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

        if ($batch = trim((string) $request->get('import_batch_number'))) {
            if ($batch === 'manual') {
                $query->where(function ($q) {
                    $q->whereNull('import_batch_number')->orWhere('import_batch_number', '');
                });
            } else {
                $query->where('import_batch_number', $batch);
            }
        }

        if ($status = $request->get('status')) {
            if (!in_array($status, ['All', 'all', ''], true)) {
                $query->where('status', $status);
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
            
            fputcsv($handle, ['name', 'email', 'phone', 'bank', 'assigned_to', 'import_batch_number', 'department_ids', 'department_names', 'tags']);
            
            $query->with('assignedTo:id,name')->chunk(200, function ($clients) use ($handle) {
                foreach ($clients as $client) {
                    $deptIds = $client->departments->pluck('id')->toArray();
                    $deptNames = $client->departments->pluck('name')->toArray();
                    
                    fputcsv($handle, [
                        $client->name,
                        $this->resolveClientDisplayEmail($client),
                        $this->resolveClientDisplayPhone($client),
                        $client->bank_name,
                        $client->assignedTo?->name,
                        $client->import_batch_number,
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
        if (!$user || !$user->canDeleteClients()) {
            abort(403, 'You are not allowed to delete clients.');
        }

        // Department-based access control for non-super admins
        $this->authorizeClientBank($user, $client, 'delete');
        $this->authorizeClientDepartment($user, $client, 'delete');
        $this->authorizeClientPortfolio($user, $client, 'delete');

        $clientId = $client->id;
        $clientName = $client->name;
        
        DB::beginTransaction();
        try {
            // Nullify related chat sessions first to satisfy foreign key constraints
            ChatSession::query()->where('client_id', $clientId)->update(['client_id' => null]);

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

    public function bulkUpdateStatus(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->canEditClients()) {
            abort(403, 'You are not allowed to update clients.');
        }

        $validated = $request->validate([
            'client_ids' => ['required', 'array'],
            'client_ids.*' => ['integer'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        $query = Client::query()->whereIn('id', $validated['client_ids']);
        $this->applyBankScope($query, $user);
        $this->applyPortfolioScope($query, $user);

        $userDepartmentIds = $user?->resolvedDepartmentIds() ?? [];
        if ($user && !$user->canManageSystemSettings() && !empty($userDepartmentIds)) {
            $query->whereHas('departments', function ($q) use ($userDepartmentIds) {
                $q->whereIn('departments.id', $userDepartmentIds);
            });
        }

        $clients = $query->get();

        if ($clients->isEmpty()) {
            return response()->json([
                'message' => 'No authorized clients found for update.',
            ], 404);
        }

        $updatedCount = 0;
        DB::beginTransaction();
        try {
            foreach ($clients as $client) {
                $client->status = $validated['status'];
                $client->save();
                $updatedCount++;
            }
            DB::commit();

            $this->audit(
                action: "Bulk updated status to {$validated['status']} for {$updatedCount} clients",
                module: 'Clients',
                meta: [
                    'updated_count' => $updatedCount,
                    'status' => $validated['status'],
                ]
            );

            return response()->json([
                'message' => 'Clients status updated successfully.',
                'updated_count' => $updatedCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update clients: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroyBatch(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->canDeleteClients()) {
            abort(403, 'You are not allowed to delete clients.');
        }

        $validated = $request->validate([
            'import_batch_number' => ['required', 'string', 'max:255'],
        ]);

        $batchNumber = trim((string) ($validated['import_batch_number'] ?? ''));
        if ($batchNumber === '') {
            return response()->json([
                'message' => 'An import batch number is required.',
            ], 422);
        }

        $query = Client::query()->with('departments');
        $this->applyBankScope($query, $user);
        $this->applyPortfolioScope($query, $user);

        $userDepartmentIds = $user?->resolvedDepartmentIds() ?? [];
        if ($user && !$user->canManageSystemSettings() && !empty($userDepartmentIds)) {
            $query->whereHas('departments', function ($q) use ($userDepartmentIds) {
                $q->whereIn('departments.id', $userDepartmentIds);
            });
        }

        $clients = $query
            ->where('import_batch_number', $batchNumber)
            ->get();

        if ($clients->isEmpty()) {
            return response()->json([
                'message' => 'No clients found for the selected import batch.',
            ], 404);
        }

        $clientIds = $clients->pluck('id')->all();
        $deletedCount = 0;

        DB::beginTransaction();
        try {
            ChatSession::whereIn('client_id', $clientIds)->update(['client_id' => null]);

            foreach ($clients as $client) {
                $client->departments()->detach();
                $client->campaigns()->detach();
                $client->delete();
                $deletedCount++;
            }

            DB::commit();

            $this->audit(
                action: "Deleted {$deletedCount} clients from import batch {$batchNumber}",
                module: 'Clients',
                meta: [
                    'import_batch_number' => $batchNumber,
                    'deleted_count' => $deletedCount,
                ]
            );

            return response()->json([
                'message' => 'Clients deleted successfully.',
                'deleted_count' => $deletedCount,
                'import_batch_number' => $batchNumber,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete clients for batch: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function bulkAssign(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->canEditClients()) {
            abort(403, 'You are not allowed to update clients.');
        }

        $validated = $request->validate([
            'client_ids' => ['required', 'array'],
            'client_ids.*' => ['integer'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $assignedToId = $this->resolveAssignedUserId($user, null, $validated['assigned_to_id'] ?? null);

        $query = Client::query()->whereIn('id', $validated['client_ids']);
        $this->applyBankScope($query, $user);
        $this->applyPortfolioScope($query, $user);

        $userDepartmentIds = $user?->resolvedDepartmentIds() ?? [];
        if ($user && !$user->canManageSystemSettings() && !empty($userDepartmentIds)) {
            $query->whereHas('departments', function ($q) use ($userDepartmentIds) {
                $q->whereIn('departments.id', $userDepartmentIds);
            });
        }

        $clients = $query->get();

        if ($clients->isEmpty()) {
            return response()->json([
                'message' => 'No authorized clients found for assignment.',
            ], 404);
        }

        $updatedCount = 0;
        DB::beginTransaction();
        try {
            foreach ($clients as $client) {
                $client->assigned_to_id = $assignedToId;
                $client->save();
                $updatedCount++;
            }
            DB::commit();

            $this->audit(
                action: "Bulk assigned {$updatedCount} clients to user " . ($assignedToId ?? 'Unassigned'),
                module: 'Clients',
                meta: [
                    'updated_count' => $updatedCount,
                    'assigned_to_id' => $assignedToId,
                ]
            );

            return response()->json([
                'message' => 'Clients assigned successfully.',
                'updated_count' => $updatedCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to assign clients: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function bulkDestroy(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->canDeleteClients()) {
            abort(403, 'You are not allowed to delete clients.');
        }

        $validated = $request->validate([
            'client_ids' => ['required', 'array'],
            'client_ids.*' => ['integer'],
        ]);

        $query = Client::query()->with('departments')->whereIn('id', $validated['client_ids']);
        $this->applyBankScope($query, $user);
        $this->applyPortfolioScope($query, $user);

        $userDepartmentIds = $user?->resolvedDepartmentIds() ?? [];
        if ($user && !$user->canManageSystemSettings() && !empty($userDepartmentIds)) {
            $query->whereHas('departments', function ($q) use ($userDepartmentIds) {
                $q->whereIn('departments.id', $userDepartmentIds);
            });
        }

        $clients = $query->get();

        if ($clients->isEmpty()) {
            return response()->json([
                'message' => 'No authorized clients found for deletion.',
            ], 404);
        }

        $clientIds = $clients->pluck('id')->all();
        $deletedCount = 0;

        DB::beginTransaction();
        try {
            ChatSession::whereIn('client_id', $clientIds)->update(['client_id' => null]);

            foreach ($clients as $client) {
                $client->departments()->detach();
                $client->campaigns()->detach();
                $client->delete();
                $deletedCount++;
            }
            DB::commit();

            $this->audit(
                action: "Bulk deleted {$deletedCount} clients",
                module: 'Clients',
                meta: [
                    'deleted_count' => $deletedCount,
                ]
            );

            return response()->json([
                'message' => 'Clients deleted successfully.',
                'deleted_count' => $deletedCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete clients: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function assignBatch(Request $request)
    {
        $user = Auth::user();
        $this->authorizeManage($user, 'update clients');

        $validated = $request->validate([
            'import_batch_number' => ['required', 'string', 'max:255'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $batchNumber = trim((string) ($validated['import_batch_number'] ?? ''));
        if ($batchNumber === '') {
            return response()->json([
                'message' => 'An import batch number is required.',
            ], 422);
        }

        $assignedToId = $this->resolveAssignedUserId($user, null, $validated['assigned_to_id'] ?? null);

        $query = Client::query()->with('departments');
        $this->applyBankScope($query, $user);
        $this->applyPortfolioScope($query, $user);

        $userDepartmentIds = $user?->resolvedDepartmentIds() ?? [];
        if ($user && !$user->canManageSystemSettings() && !empty($userDepartmentIds)) {
            $query->whereHas('departments', function ($q) use ($userDepartmentIds) {
                $q->whereIn('departments.id', $userDepartmentIds);
            });
        }

        $clients = $query
            ->where('import_batch_number', $batchNumber)
            ->get();

        if ($clients->isEmpty()) {
            return response()->json([
                'message' => 'No clients found for the selected import batch.',
            ], 404);
        }

        $updatedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($clients as $client) {
                $client->assigned_to_id = $assignedToId;
                $client->save();
                $updatedCount++;
            }

            DB::commit();

            $this->audit(
                action: "Assigned {$updatedCount} clients from import batch {$batchNumber} to user " . ($assignedToId ?? 'Unassigned'),
                module: 'Clients',
                meta: [
                    'import_batch_number' => $batchNumber,
                    'updated_count' => $updatedCount,
                    'assigned_to_id' => $assignedToId,
                ]
            );

            return response()->json([
                'message' => 'Clients assigned successfully.',
                'updated_count' => $updatedCount,
                'import_batch_number' => $batchNumber,
                'assigned_to_id' => $assignedToId,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to assign clients for batch: ' . $e->getMessage(),
            ], 500);
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
        if (!$user || !$user->canViewClients()) {
            abort(403, 'You are not allowed to access clients.');
        }
    }

    protected function authorizeManage($user, string $action = 'manage clients'): void
    {
        if (!$user || !$user->canEditClients()) {
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
        if ($user && !$user->canAccessAllBanks() && !empty($user->resolvedBankIds())) {
            $query->whereIn('bank_id', $user->resolvedBankIds());
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

        if (!empty($user->resolvedBankIds()) && !in_array((int) $client->bank_id, $user->resolvedBankIds(), true)) {
            abort(403, 'You do not have permission to act on this client.');
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

        $ids = $user->resolvedBankIds();
        if (empty($ids)) {
            abort(422, 'Your user account is not assigned to a bank.');
        }

        if ($requestedBankId && in_array((int) $requestedBankId, $ids, true)) {
            return (int) $requestedBankId;
        }

        return $ids[0];
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
        $extension = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $sample = file_get_contents($path, false, null, 0, 4096) ?: '';

        if ($extension !== 'xlsx' && str_contains($sample, "\0")) {
            abort(422, 'Import failed: binary file content detected. Only CSV or Excel (.xlsx) uploads are allowed.');
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
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ];

        if ($mime && !in_array($mime, $allowedMimes, true)) {
            abort(422, "Import failed: unsupported file type ({$mime}) for {$originalName}.");
        }
    }

    protected function normalizeImportHeader($header): array
    {
        return collect($header ?? [])
            ->map(fn ($column) => $this->normalizeImportColumnName($column))
            ->all();
    }

    protected function isValidImportHeader(array $header): bool
    {
        return in_array('name', $header, true);
    }

    protected function resolveImportDepartmentIds($user, array $departmentIds): array
    {
        $resolved = collect($departmentIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($resolved)) {
            abort(422, 'Select at least one department for this import.');
        }

        $allowedDepartmentIds = $user?->resolvedDepartmentIds() ?? [];
        if ($user && !$user->canManageSystemSettings() && !empty($allowedDepartmentIds)) {
            $invalid = array_diff($resolved, $allowedDepartmentIds);
            if (!empty($invalid)) {
                abort(403, 'You are not allowed to import clients into one or more selected departments.');
            }
        }

        return $resolved;
    }

    protected function readImportRows(string $path, string $originalName): array
    {
        $extension = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($extension) {
            'xlsx' => $this->readXlsxRows($path),
            default => $this->readCsvRows($path),
        };
    }

    protected function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            abort(422, 'Import failed: unable to read the uploaded CSV file.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    protected function readXlsxRows(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            abort(422, 'Import failed: this server cannot read Excel files because ZipArchive is not available.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            abort(422, 'Import failed: unable to open the Excel workbook.');
        }

        $sheetPath = $this->resolveFirstWorksheetPath($zip);
        $sheetXml = $sheetPath ? $zip->getFromName($sheetPath) : false;
        if ($sheetXml === false) {
            $zip->close();
            abort(422, 'Import failed: no worksheet data was found in the Excel workbook.');
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $sharedStringsDoc = @simplexml_load_string($sharedStringsXml);
            if ($sharedStringsDoc) {
                foreach ($sharedStringsDoc->si ?? [] as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string) $si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $run) {
                            $text .= (string) ($run->t ?? '');
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetDoc = @simplexml_load_string($sheetXml);
        if (!$sheetDoc) {
            $zip->close();
            abort(422, 'Import failed: unable to parse the Excel worksheet.');
        }

        $sheetDoc->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($sheetDoc->xpath('//main:sheetData/main:row') ?: [] as $rowNode) {
            $cells = [];
            $maxIndex = -1;

            foreach ($rowNode->c as $cell) {
                $reference = (string) ($cell['r'] ?? '');
                preg_match('/([A-Z]+)/', $reference, $matches);
                $columnLetters = $matches[1] ?? 'A';
                $columnIndex = $this->excelColumnLettersToIndex($columnLetters);
                $maxIndex = max($maxIndex, $columnIndex);
                $cells[$columnIndex] = $this->extractExcelCellValue($cell, $sharedStrings);
            }

            if ($maxIndex < 0) {
                continue;
            }

            $row = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $row[] = $cells[$i] ?? '';
            }

            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = $row;
        }

        $zip->close();

        return $rows;
    }

    protected function resolveFirstWorksheetPath(\ZipArchive $zip): ?string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = @simplexml_load_string($workbookXml);
        $rels = @simplexml_load_string($relsXml);

        if (!$workbook || !$rels) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rels->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $firstSheet = $workbook->xpath('//main:sheets/main:sheet[1]');
        if (empty($firstSheet)) {
            return 'xl/worksheets/sheet1.xml';
        }

        $relationshipId = (string) $firstSheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        foreach ($rels->xpath('//rel:Relationship') ?: [] as $relationship) {
            if ((string) $relationship['Id'] !== $relationshipId) {
                continue;
            }

            $target = ltrim((string) $relationship['Target'], '/');
            return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
        }

        return 'xl/worksheets/sheet1.xml';
    }

    protected function excelColumnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    protected function extractExcelCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            return trim((string) ($cell->is->t ?? ''));
        }

        $value = (string) ($cell->v ?? '');

        if ($type === 's') {
            $sharedIndex = (int) $value;
            return trim((string) ($sharedStrings[$sharedIndex] ?? ''));
        }

        if ($type === 'b') {
            return $value === '1' ? '1' : '0';
        }

        return trim($value);
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

    protected function resolveClientDisplayEmail(Client $client): ?string
    {
        return $this->cleanImportString($client->email);
    }

    protected function resolveClientDisplayPhone(Client $client): ?string
    {
        return $this->firstNonEmptyImportValue([
            $client->cell_phone,
            $client->phone,
            $client->home_phone,
            $client->work_phone,
        ]);
    }

    protected function normalizeImportColumnName($column): string
    {
        $normalized = mb_strtolower(trim((string) $column));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        $aliases = [
            'emailpersonal' => 'email_personal',
            'easy_pay' => 'easy_pay_number',
            'easypay' => 'easy_pay_number',
            'idno' => 'id_number',
            'known_as' => 'name',
            'patient_cell' => 'cell_phone',
            'patient_home' => 'home_phone',
            'patient_work' => 'work_phone',
            'patient_email_personal' => 'email_personal',
            'patient_email_work' => 'email_work',
            'cell_no' => 'cell',
            'home_no' => 'home',
            'work_no' => 'work',
            'outstandingbalance' => 'outstanding_balance',
            'installmentamount' => 'installment_amount',
            'arrearsamount' => 'arrears_amount',
            'settlement' => 'settlement_amount',
            'settlementamount' => 'settlement_amount',
            'settlement_amount' => 'settlement_amount',
            '3_months' => 'three_months_amount',
            '3_month' => 'three_months_amount',
            '3months' => 'three_months_amount',
            '3month' => 'three_months_amount',
            'three_months' => 'three_months_amount',
            'three_month' => 'three_months_amount',
            'three_months_amount' => 'three_months_amount',
            'lastpaymentamount' => 'last_payment_amount',
            'totalpaymentamount' => 'total_payment_amount',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    protected function cleanImportString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    protected function firstNonEmptyImportValue(array $values): ?string
    {
        foreach ($values as $value) {
            $clean = $this->cleanImportString($value);
            if ($clean !== null) {
                return $clean;
            }
        }

        return null;
    }

    protected function parseImportAmount($value): ?string
    {
        $text = $this->cleanImportString($value);
        if ($text === null) {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', ''], $text);
        $normalized = preg_replace('/[^0-9.\-]/', '', $normalized) ?? '';
        if ($normalized === '' || $normalized === '-' || !is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    protected function extendImportExecutionLimits(): void
    {
        @ini_set('memory_limit', (string) env('IMPORT_MEMORY_LIMIT', '1024M'));
        @ini_set('max_execution_time', '0');

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    protected function refreshImportExecutionWindow(int $seconds = 30): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
    }

    protected function generateImportBatchNumber(): string
    {
        do {
            $batchNumber = 'IMP-' . now()->format('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } while (ImportUpload::query()->where('import_batch_number', $batchNumber)->exists());

        return $batchNumber;
    }
}
