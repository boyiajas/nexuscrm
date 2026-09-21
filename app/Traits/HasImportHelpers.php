<?php

namespace App\Traits;

use Illuminate\Support\Str;
use ZipArchive;
use SimpleXMLElement;
use App\Models\Client;
use App\Models\ImportUpload;
use App\Models\User;
use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

trait HasImportHelpers
{
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
        $header = is_array($header) ? $header : [];
        while (!empty($header) && trim((string) end($header)) === '') {
            array_pop($header);
        }

        return collect($header)
            ->map(fn ($column) => $this->normalizeImportColumnName($column))
            ->all();
    }

    protected function isValidImportHeader(array $header): bool
    {
        $normalized = $this->normalizeImportHeader($header);

        return empty($this->missingRequiredImportColumns($normalized));
    }

    protected function hasRequiredNameColumn(array $normalizedHeader): bool
    {
        $nameKeys = [
            'name',
            'first_name',
            'firstname',
            'full_name',
            'fullname',
            'client_name',
            'clientname',
            'customer_name',
            'debtor_name',
            'debtor',
            'account_name',
            'known_as',
            'surname',
            'last_name',
            'lastname',
        ];

        foreach ($nameKeys as $key) {
            if (in_array($key, $normalizedHeader, true)) {
                return true;
            }
        }

        return false;
    }

    protected function acceptedRequiredNameHeaders(): array
    {
        return [
            'Name',
            'First Name',
            'Full Name',
            'Client Name',
            'Debtor Name',
            'Debtor',
            'Customer Name',
            'Surname',
            'Last Name',
            'Known As',
        ];
    }

    protected function requiredImportColumns(): array
    {
        return ['name'];
    }

    protected function supportedImportColumns(): array
    {
        return [
            'acc_code',
            'account_number',
            'account_type',
            'arrears_amount',
            'bank_name',
            'branch_code',
            'cell',
            'cell_phone',
            'department',
            'department_ids',
            'easy_pay_number',
            'email',
            'email_personal',
            'email_work',
            'first_name',
            'home',
            'home_phone',
            'id_number',
            'initials',
            'installment_amount',
            'known_as',
            'last_payment_amount',
            'name',
            'old_account_number',
            'outstanding_balance',
            'patient_email_personal',
            'patient_email_work',
            'phone',
            'settlement_amount',
            'store_number',
            'surname',
            'tags',
            'three_months_amount',
            'title',
            'total_payment_amount',
            'type',
            'whatsapp_contact_basis',
            'whatsapp_contact_basis_details',
            'whatsapp_opt_in_source',
            'whatsapp_opted_in_at',
            'work',
            'work_phone',
        ];
    }

    protected function importColumnAliases(): array
    {
        return [
            // Name aliases
            'full_name' => 'name',
            'fullname' => 'name',
            'client_name' => 'name',
            'clientname' => 'name',
            'customer_name' => 'name',
            'debtor_name' => 'name',
            'debtor' => 'name',
            'account_name' => 'name',
            'contact_name' => 'name',
            'firstname' => 'first_name',
            'first_name' => 'first_name',
            'first_names' => 'first_name',
            'lastname' => 'surname',
            'last_name' => 'surname',
            'known_as' => 'name',

            // ID Number aliases
            'idno' => 'id_number',
            'id_no' => 'id_number',
            'id_num' => 'id_number',
            'idnumber' => 'id_number',
            'identity_number' => 'id_number',
            'sa_id' => 'id_number',
            'sa_id_number' => 'id_number',

            // Account aliases
            'account_number' => 'account_number',
            'account_no' => 'account_number',
            'accountno' => 'account_number',
            'acc_no' => 'account_number',
            'acc_code' => 'acc_code',
            'account_num' => 'account_number',
            'acc_num' => 'account_number',
            'acct_no' => 'account_number',
            'acct_number' => 'account_number',
            'old_account_number' => 'old_account_number',

            // Phone aliases
            'phone_number' => 'phone',
            'phonenumber' => 'phone',
            'contact_number' => 'phone',
            'cell_no' => 'cell_phone',
            'cellphone' => 'cell_phone',
            'cell_phone' => 'cell_phone',
            'cell_number' => 'cell_phone',
            'cellnumber' => 'cell_phone',
            'mobile' => 'cell_phone',
            'mobile_number' => 'cell_phone',
            'mobile_no' => 'cell_phone',
            'mobile_phone' => 'cell_phone',
            'patient_cell' => 'cell_phone',
            'patient_home' => 'home_phone',
            'home_no' => 'home_phone',
            'home_phone' => 'home_phone',
            'home_number' => 'home_phone',
            'patient_work' => 'work_phone',
            'work_no' => 'work_phone',
            'work_phone' => 'work_phone',
            'work_number' => 'work_phone',

            // Email aliases
            'email_address' => 'email',
            'emailaddress' => 'email',
            'emailpersonal' => 'email_personal',
            'email_personal' => 'email_personal',
            'patient_email_personal' => 'email_personal',
            'patient_email_work' => 'email_work',
            'email_work' => 'email_work',

            // EasyPay and Store
            'easy_pay' => 'easy_pay_number',
            'easypay' => 'easy_pay_number',
            'easypay_number' => 'easy_pay_number',
            'easy_pay_no' => 'easy_pay_number',
            'store_number' => 'store_number',
            'storenumber' => 'store_number',
            'store_no' => 'store_number',
            'store_code' => 'store_number',
            'branch_code' => 'branch_code',
            'branchcode' => 'branch_code',
            'branch_no' => 'branch_code',

            // Financial amounts
            'outstandingbalance' => 'outstanding_balance',
            'outstanding_balance' => 'outstanding_balance',
            'balance' => 'outstanding_balance',
            'current_balance' => 'outstanding_balance',
            'currentbalance' => 'outstanding_balance',
            'cur_bal' => 'outstanding_balance',
            'current_liability' => 'outstanding_balance',
            'liability' => 'outstanding_balance',
            'total_due' => 'outstanding_balance',
            'installmentamount' => 'installment_amount',
            'installment_amount' => 'installment_amount',
            'instalment' => 'installment_amount',
            'instalment_amount' => 'installment_amount',
            'arrearsamount' => 'arrears_amount',
            'arrears_amount' => 'arrears_amount',
            'arrears' => 'arrears_amount',
            'arrear_amount' => 'arrears_amount',
            'lastpaymentamount' => 'last_payment_amount',
            'last_payment_amount' => 'last_payment_amount',
            'totalpaymentamount' => 'total_payment_amount',
            'total_payment_amount' => 'total_payment_amount',
            'settlement' => 'settlement_amount',
            'settlementamount' => 'settlement_amount',
            'settlement_amount' => 'settlement_amount',
            '3_months' => 'three_months_amount',
            '3_month' => 'three_months_amount',
            '3months' => 'three_months_amount',
            '3month' => 'three_months_amount',
            'three_months' => 'three_months_amount',
        ];
    }

    protected function missingRequiredImportColumns(array $normalizedHeader): array
    {
        if ($this->hasRequiredNameColumn($normalizedHeader)) {
            return [];
        }

        return ['name'];
    }

    protected function importFieldLabel(string $field): string
    {
        $labels = [
            'name' => 'Client Name',
            'first_name' => 'First Name',
            'surname' => 'Surname',
            'title' => 'Title',
            'initials' => 'Initials',
            'id_number' => 'ID Number',
            'phone' => 'Phone',
            'cell' => 'Cell Phone',
            'cell_phone' => 'Cell Phone',
            'home' => 'Home Phone',
            'home_phone' => 'Home Phone',
            'work' => 'Work Phone',
            'work_phone' => 'Work Phone',
            'email' => 'Email',
            'email_personal' => 'Personal Email',
            'email_work' => 'Work Email',
            'bank_name' => 'Bank Name',
            'account_number' => 'Account Number',
            'acc_code' => 'Account Code',
            'old_account_number' => 'Old Account Number',
            'account_type' => 'Account Type',
            'type' => 'Type / Book',
            'branch_code' => 'Branch Code',
            'easy_pay_number' => 'EasyPay Number',
            'store_number' => 'Store Number',
            'outstanding_balance' => 'Outstanding Balance',
            'arrears_amount' => 'Arrears Amount',
            'installment_amount' => 'Installment Amount',
            'settlement_amount' => 'Settlement Amount',
            'three_months_amount' => '3-Month Settlement',
            'last_payment_amount' => 'Last Payment Amount',
            'total_payment_amount' => 'Total Payment Amount',
            'department' => 'Department',
            'department_ids' => 'Department IDs',
            'tags' => 'Tags',
            'whatsapp_contact_basis' => 'WhatsApp Basis',
            'whatsapp_contact_basis_details' => 'WhatsApp Basis Details',
            'whatsapp_opt_in_source' => 'WhatsApp Opt-In Source',
            'whatsapp_opted_in_at' => 'WhatsApp Opt-In Date',
        ];

        return $labels[$field] ?? Str::title(str_replace('_', ' ', $field));
    }

    protected function buildImportHeaderDiagnostics($rawHeader): array
    {
        $rawColumns = collect($rawHeader ?? [])
            ->map(fn ($column) => preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $column)))
            ->filter(fn ($column) => $column !== '')
            ->values()
            ->all();

        $normalizedHeader = $this->normalizeImportHeader($rawHeader ?? []);
        $supported = $this->supportedImportColumns();

        $recognizedColumns = [];
        $unrecognizedColumns = [];
        foreach ($rawColumns as $index => $rawCol) {
            $normCol = $normalizedHeader[$index] ?? '';
            if ($normCol !== '' && in_array($normCol, $supported, true)) {
                $recognizedColumns[] = [
                    'raw' => $rawCol,
                    'normalized' => $normCol,
                    'label' => $this->importFieldLabel($normCol),
                ];
            } else {
                $unrecognizedColumns[] = $rawCol;
            }
        }

        $missingRequired = $this->missingRequiredImportColumns($normalizedHeader);
        $isValid = empty($missingRequired);

        return [
            'is_valid' => $isValid,
            'has_required_name' => $this->hasRequiredNameColumn($normalizedHeader),
            'required_headers' => $this->requiredImportColumns(),
            'accepted_required_headers' => $this->acceptedRequiredNameHeaders(),
            'missing_required_headers' => $missingRequired,
            'detected_headers' => $rawColumns,
            'normalized_detected_headers' => array_values(array_unique(array_filter($normalizedHeader))),
            'recognized_columns' => $recognizedColumns,
            'unsupported_headers' => array_values(array_unique($unrecognizedColumns)),
            'supported_headers' => $supported,
            'total_detected' => count($rawColumns),
            'total_recognized' => count($recognizedColumns),
        ];
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
        if ($user && !$user->canAccessAllBanks() && !$user->isSuperAdmin() && !$user->isAdmin()) {
            if (empty($allowedDepartmentIds)) {
                abort(403, 'Your user account is not assigned to a department.');
            }

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

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
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

        $aliases = $this->importColumnAliases();

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
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    public function updateOptIn(Request $request, Client $client)
    {
        $user = Auth::user();
        if (!$user || !$user->canEditClients()) {
            abort(403, 'You do not have permission to update client opt-in status.');
        }

        if (method_exists($this, 'authorizeClientScopeForUser')) {
            $this->authorizeClientScopeForUser($user, $client, 'update');
        }

        $data = $request->validate([
            'opt_in' => ['required', 'string', Rule::in(['yes', 'no', 'none'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $client->setOptIn($data['opt_in'], $data['reason'] ?? 'Manual status update');

        $this->audit(
            action: "Updated client #{$client->id} Opt-In status to {$data['opt_in']}",
            module: 'Clients',
            meta: [
                'client_id' => $client->id,
                'opt_in' => $data['opt_in'],
                'reason' => $data['reason'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Opt-in status updated successfully',
            'client' => array_merge($client->toArray(), [
                'opt_in' => $client->opt_in,
                'opt_in_updated_at' => optional($client->opt_in_updated_at)->toDateTimeString(),
                'whatsapp_opted_out_at' => optional($client->whatsapp_opted_out_at)->toDateTimeString(),
                'whatsapp_opted_in_at' => optional($client->whatsapp_opted_in_at)->toDateTimeString(),
            ]),
        ]);
    }

    protected function generateImportBatchNumber(): string
    {
        do {
            $batchNumber = 'IMP-' . now()->format('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } while (ImportUpload::query()->where('import_batch_number', $batchNumber)->exists());

        return $batchNumber;
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

        if ($bankId && !$assignee->canAccessBankId($bankId)) {
            abort(422, 'The selected assignee must belong to the same bank as the client.');
        }

        if (!$user?->canAccessAllBanks() && !$user?->isSuperAdmin() && empty(array_intersect($user?->accessibleBankIds() ?? [], $assignee->accessibleBankIds()))) {
            abort(422, 'The selected assignee must belong to your assigned bank scope.');
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
}
