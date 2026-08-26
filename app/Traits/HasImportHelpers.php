<?php

namespace App\Traits;

use Illuminate\Support\Str;
use ZipArchive;
use SimpleXMLElement;
use App\Models\Client;
use App\Models\ImportUpload;
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
        if ($user && !$user->canViewAllImportedClients() && !empty($allowedDepartmentIds)) {
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
            'lastpaymentamount' => 'last_payment_amount',
            'totalpaymentamount' => 'total_payment_amount',
            'settlement' => 'settlement_amount',
            'settlementamount' => 'settlement_amount',
            'settlement_amount' => 'settlement_amount',
            '3_months' => 'three_months_amount',
            '3_month' => 'three_months_amount',
            '3months' => 'three_months_amount',
            '3month' => 'three_months_amount',
            'three_months' => 'three_months_amount',
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
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');

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

    public function updateOptIn(Request $request, Client $client)
    {
        $user = Auth::user();
        if (!$user || !$user->canEditClients()) {
            abort(403, 'You do not have permission to update client opt-in status.');
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

}
