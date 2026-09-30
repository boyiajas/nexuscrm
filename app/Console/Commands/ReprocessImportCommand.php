<?php

namespace App\Console\Commands;

use App\Jobs\ImportClientsJob;
use App\Models\Client;
use App\Models\ImportUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ReprocessImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:reprocess-import
                            {batch? : The import batch number (e.g. IMP-20260930-210624-4D5CE2)}
                            {--file= : Override path to CSV or Excel file if moved}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocess an uploaded client import file to extract and update missing fields like PTP Due Date and PTP Amount';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== NexusCRM Client Import Reprocessor ===');

        // 1. Verify schema migrations
        if (!Schema::hasColumn('clients', 'ptp_due_date') || !Schema::hasColumn('clients', 'ptp_amount')) {
            $this->warn('Columns ptp_due_date / ptp_amount are missing from clients table. Running migrations...');
            Artisan::call('migrate', ['--force' => true]);
            $this->line(Artisan::output());
        } else {
            $this->info('✓ Database schema verified (ptp_due_date and ptp_amount exist).');
        }

        // 2. Find upload record
        $batch = trim((string) $this->argument('batch'));
        $query = ImportUpload::query()->where('dataset', 'clients');

        if ($batch !== '') {
            $query->where('import_batch_number', $batch);
        } else {
            $query->latest();
        }

        $upload = $query->first();

        if (!$upload) {
            $this->error($batch !== '' 
                ? "No import upload record found for batch '{$batch}'." 
                : "No client import upload records found in database.");
            return 1;
        }

        $this->info("Found batch: {$upload->import_batch_number} (File: {$upload->original_filename})");

        // 3. Resolve file path
        $filePath = $this->option('file') ?: $this->resolveUploadFilePath($upload);

        if (!$filePath || !file_exists($filePath)) {
            $this->error("Upload file could not be found on disk.");
            if ($upload->stored_path) {
                $this->line("Stored path in database: {$upload->stored_path}");
            }
            $this->line("Use --file=/path/to/file.csv to specify the file location explicitly.");
            return 1;
        }

        $this->info("Using file: {$filePath}");

        // 4. Run ImportClientsJob synchronously
        $this->info('Reprocessing import rows and updating client records...');

        $job = new ImportClientsJob(
            $upload->id,
            $upload->user_id,
            $upload->bank_id,
            $upload->department_ids ?? [],
            $filePath,
            $upload->original_filename,
            $upload->import_batch_number,
            ['status' => 'skipped']
        );

        try {
            $job->handle();
        } catch (\Throwable $e) {
            $this->error('Reprocessing failed: ' . $e->getMessage());
            return 1;
        }

        // 5. Restart background queue workers
        $this->info('Restarting background queue workers to reload updated classes...');
        Artisan::call('queue:restart');

        // 6. Report results
        $upload->refresh();
        $totalClients = Client::where('import_batch_number', $upload->import_batch_number)->count();
        $withPtpDate = Client::where('import_batch_number', $upload->import_batch_number)
            ->whereNotNull('ptp_due_date')
            ->count();
        $withPtpAmount = Client::where('import_batch_number', $upload->import_batch_number)
            ->whereNotNull('ptp_amount')
            ->count();

        $this->newLine();
        $this->info('=== Reprocessing Complete ===');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Batch Number', $upload->import_batch_number],
                ['Import Status', $upload->import_status],
                ['Total Clients in Batch', $totalClients],
                ['Clients with PTP Due Date', $withPtpDate],
                ['Clients with PTP Amount', $withPtpAmount],
            ]
        );

        $samples = Client::where('import_batch_number', $upload->import_batch_number)
            ->where(function ($q) {
                $q->whereNotNull('ptp_due_date')->orWhereNotNull('ptp_amount');
            })
            ->limit(3)
            ->get();

        if ($samples->isNotEmpty()) {
            $this->newLine();
            $this->info('Sample Clients from Batch:');
            $sampleRows = $samples->map(fn ($c) => [
                $c->name,
                $c->account_number_masked ?: $c->account_number,
                $c->ptp_due_date?->format('Y-m-d') ?: '-',
                $c->ptp_amount ? 'R' . $c->ptp_amount : '-',
                $c->activation_amount ? 'R' . $c->activation_amount : '-',
            ])->all();

            $this->table(['Client Name', 'Account', 'PTP Due Date', 'PTP Amount', 'Activation Amount'], $sampleRows);
        }

        return 0;
    }

    protected function resolveUploadFilePath(ImportUpload $upload): ?string
    {
        $candidates = [
            $upload->stored_path,
            Storage::disk('local')->path((string) $upload->stored_path),
            storage_path('app/' . ltrim((string) $upload->stored_path, '/')),
            storage_path('app/private/' . ltrim((string) $upload->stored_path, '/')),
            base_path('docs/' . $upload->original_filename),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
