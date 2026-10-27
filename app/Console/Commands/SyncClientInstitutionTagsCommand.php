<?php

namespace App\Console\Commands;

use App\Services\ClientInstitutionService;
use Illuminate\Console\Command;

class SyncClientInstitutionTagsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:sync-institution-tags {--bank= : Filter by specific Bank ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize associated institution bank tags across clients belonging to multiple banks';

    /**
     * Execute the console command.
     */
    public function handle(ClientInstitutionService $service): int
    {
        $bankId = $this->option('bank') ? (int) $this->option('bank') : null;

        $this->info('Starting cross-institution bank tag synchronization...');
        if ($bankId) {
            $this->line("Filtering by Bank ID: {$bankId}");
        }

        $processed = $service->syncAllClientInstitutionTags($bankId);

        $this->info("Successfully processed and synchronized tags for {$processed} client(s).");

        return self::SUCCESS;
    }
}
