<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Department;
use App\Models\ImportUpload;
use App\Models\User;
use App\Traits\HasImportHelpers;
use App\Concerns\HasAuditLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportClientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasImportHelpers, HasAuditLogging;

    public $timeout = 3600;

    protected int $importUploadId;
    protected int $userId;
    protected ?int $bankId;
    protected array $selectedDepartmentIds;
    protected string $path;
    protected string $originalName;
    protected string $importBatchNumber;
    protected array $scanResult;

    public function __construct(
        int $importUploadId,
        int $userId,
        ?int $bankId,
        array $selectedDepartmentIds,
        string $path,
        string $originalName,
        string $importBatchNumber,
        array $scanResult
    ) {
        $this->importUploadId = $importUploadId;
        $this->userId = $userId;
        $this->bankId = $bankId;
        $this->selectedDepartmentIds = $selectedDepartmentIds;
        $this->path = $path;
        $this->originalName = $originalName;
        $this->importBatchNumber = $importBatchNumber;
        $this->scanResult = $scanResult;
    }

    public function handle(): void
    {
        $importUpload = ImportUpload::find($this->importUploadId);
        if (!$importUpload) {
            return;
        }

        $user = User::find($this->userId);
        if (!$user) {
            $importUpload->update(['import_status' => 'import_failed', 'error_message' => 'User not found.']);
            return;
        }

        $importUpload->update(['import_status' => 'importing']);

        try {
            $this->extendImportExecutionLimits();
            $rows = $this->readImportRows($this->path, $this->originalName);
            $header = array_shift($rows);
            $normalizedHeader = $this->normalizeImportHeader($header);

            if (empty($normalizedHeader) || !$this->isValidImportHeader($normalizedHeader)) {
                throw new \Exception('Import failed: the file header is invalid. Expected at least a name column and only supported client fields.');
            }

            $totalRows = count($rows);
            $importCount = 0;
            $createdCount = 0;
            $updatedCount = 0;
            $duplicateCount = 0;
            $skippedCount = 0;
            $errors = [];
            $seenEmails = [];
            $seenPhones = [];
            
            $bankId = $this->bankId;
            $bankName = $this->resolveBankName($bankId, null);
            $defaultAssignedToId = $this->resolveAssignedUserId($user, $bankId, null);
            $departmentLookup = Department::query()
                ->pluck('id', 'name')
                ->mapWithKeys(function ($id, $name) {
                    return [mb_strtolower(trim((string) $name)) => (int) $id];
                })
                ->all();

            $rowNumber = 1;
            foreach ($rows as $row) {
                $rowNumber++;
                
                // Update progress every 50 rows
                if ($rowNumber % 50 === 0) {
                    $this->refreshImportExecutionWindow();
                    $importUpload->update([
                        'import_summary' => [
                            'total_rows' => $totalRows,
                            'processed_rows' => $rowNumber - 1,
                            'imported' => $importCount,
                        ]
                    ]);
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
                    $errors[] = "Row {$rowNumber} skipped: duplicate email found in import file ({$emailValue}).";
                    $duplicateCount++;
                    continue;
                }

                if ($normalizedPhone && isset($seenPhones[$normalizedPhone])) {
                    $errors[] = "Row {$rowNumber} skipped: duplicate phone found in import file ({$primaryPhone}).";
                    $duplicateCount++;
                    continue;
                }

                if ($normalizedEmail) {
                    $seenEmails[$normalizedEmail] = true;
                }

                if ($normalizedPhone) {
                    $seenPhones[$normalizedPhone] = true;
                }

                $departmentIds = [];
                if (!empty($data['department_ids'])) {
                    if (preg_match('/^\d+(,\d+)*$/', $data['department_ids'])) {
                        $departmentIds = array_map('intval', explode(',', $data['department_ids']));
                    } else {
                        $deptNames = array_map('trim', explode(',', $data['department_ids']));
                        foreach ($deptNames as $deptName) {
                            $resolvedDeptId = $departmentLookup[mb_strtolower($deptName)] ?? null;
                            if ($resolvedDeptId) {
                                $departmentIds[] = $resolvedDeptId;
                            }
                        }
                    }
                }

                if (empty($departmentIds) && !empty($data['department'])) {
                    $resolvedDeptId = $departmentLookup[mb_strtolower(trim((string) $data['department']))] ?? null;
                    if ($resolvedDeptId) {
                        $departmentIds = [$resolvedDeptId];
                    }
                }

                $departmentIds = array_values(array_unique(array_merge($this->selectedDepartmentIds, $departmentIds)));

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
                    'account_type' => $this->cleanImportString($data['account_type'] ?? null),
                    'type' => $this->cleanImportString($data['type'] ?? null),
                    'easy_pay_number' => $this->cleanImportString($data['easy_pay_number'] ?? null),
                    'branch_code' => $this->cleanImportString($data['branch_code'] ?? null),
                    'arrears_amount' => $this->parseImportAmount($data['arrears_amount'] ?? null),
                    'outstanding_balance' => $this->parseImportAmount($data['outstanding_balance'] ?? null),
                    'settlement_amount' => $this->parseImportAmount($data['settlement_amount'] ?? null),
                    'three_months_amount' => $this->parseImportAmount($data['three_months_amount'] ?? null),
                    'installment_amount' => $this->parseImportAmount($data['installment_amount'] ?? null),
                    'last_payment_amount' => $this->parseImportAmount($data['last_payment_amount'] ?? null),
                    'total_payment_amount' => $this->parseImportAmount($data['total_payment_amount'] ?? null),
                    'import_batch_number' => $this->importBatchNumber,
                    'whatsapp_contact_basis' => $data['whatsapp_contact_basis'] ?? 'bank_instruction',
                    'whatsapp_contact_basis_details' => $data['whatsapp_contact_basis_details'] ?? 'Imported from bank-provided debtor list.',
                    'whatsapp_opted_in_at' => $data['whatsapp_opted_in_at'] ?? null,
                    'whatsapp_opt_in_source' => $data['whatsapp_opt_in_source'] ?? 'bank_import',
                    'assigned_to_id' => $defaultAssignedToId,
                    'tags' => isset($data['tags']) ? array_filter(array_map('trim', explode(',', $data['tags']))) : [],
                ];

                $matchAttributes = null;
                if (!empty($clientData['account_number'])) {
                    $matchAttributes = ['bank_id' => $bankId, 'account_number' => $clientData['account_number']];
                } elseif (!empty($clientData['easy_pay_number'])) {
                    $matchAttributes = ['bank_id' => $bankId, 'easy_pay_number' => $clientData['easy_pay_number']];
                } elseif (!empty($emailValue)) {
                    $matchAttributes = ['bank_id' => $bankId, 'email' => $emailValue];
                }

                if ($matchAttributes) {
                    $existing = Client::query()->where($matchAttributes)->first();
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
                    $client = Client::create(array_merge($clientData, [
                        'email' => 'import_' . time() . '_' . $importCount . '@example.com',
                    ]));
                    $createdCount++;
                }

                if (!empty($departmentIds)) {
                    $client->departments()->sync($departmentIds);
                }

                $importCount++;
            }

            $importUpload->forceFill([
                'import_status' => 'imported',
                'imported_at' => now(),
                'import_summary' => [
                    'total_rows' => $totalRows,
                    'processed_rows' => $totalRows,
                    'imported' => $importCount,
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'duplicates' => $duplicateCount,
                    'skipped' => $skippedCount,
                    'errors' => $errors,
                ],
            ])->save();

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
                    'file' => $this->originalName,
                    'import_batch_number' => $this->importBatchNumber,
                    'department_ids' => $this->selectedDepartmentIds,
                    'malware_scan' => $this->scanResult,
                ]
            );

        } catch (Throwable $e) {
            Log::error('ImportClientsJob failed: ' . $e->getMessage(), ['exception' => $e]);
            
            $importUpload->forceFill([
                'import_status' => 'import_failed',
                'error_message' => $e->getMessage(),
            ])->save();
            
            $this->audit(
                action: "Client import failed",
                module: 'Clients',
                meta: [
                    'error' => $e->getMessage(),
                    'file' => $this->originalName,
                    'upload_id' => $importUpload->id,
                    'import_batch_number' => $this->importBatchNumber,
                ]
            );
            
            throw $e;
        }
    }
}
