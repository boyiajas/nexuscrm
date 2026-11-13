<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Models\ChatSession;
use App\Services\BankWabaResolver;
use Illuminate\Console\Command;

class SyncChatWabaNumbersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:sync-waba-numbers
                            {--client-id= : Target a specific client ID}
                            {--dry-run : Inspect what would change without modifying the database}
                            {--force : Force sync even if session already has a WABA number}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync WhatsApp chat session WABA numbers and bank IDs with their assigned client bank configuration';

    /**
     * Execute the console command.
     */
    public function handle(BankWabaResolver $resolver): int
    {
        $clientId = $this->option('client-id');
        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = ChatSession::with(['client.bank.whatsappAccount'])
            ->where('platform', 'whatsapp');

        if ($clientId) {
            $query->where('client_id', (int) $clientId);
        }

        $sessions = $query->get();

        if ($sessions->isEmpty()) {
            $this->info('No WhatsApp chat sessions found matching criteria.');
            return self::SUCCESS;
        }

        $this->info("Found {$sessions->count()} WhatsApp chat session(s) to inspect.");
        if ($isDryRun) {
            $this->warn('[DRY-RUN MODE] No database changes will be committed.');
        }

        $tableRows = [];
        $updatedCount = 0;

        foreach ($sessions as $session) {
            $client = $session->client;
            if (!$client) {
                continue;
            }

            $bank = $client->bank;
            if (!$bank && $client->bank_id) {
                $bank = Bank::with('whatsappAccount')->find($client->bank_id);
            }

            if (!$bank) {
                continue;
            }

            $targetWaba = $bank->whatsappAccount?->phone_number_id
                ?: $bank->primary_whatsapp_number;

            $currentWaba = $session->waba_phone_number_id;
            $currentBankId = $session->bank_id;
            $targetBankId = $bank->id;

            $wabaMatches = !empty($currentWaba) && !empty($targetWaba) && (
                (string) $currentWaba === (string) $targetWaba ||
                $resolver->phonesMatch($currentWaba, $targetWaba)
            );

            $needsUpdate = false;
            $updates = [];

            if ($currentBankId !== $targetBankId) {
                $updates['bank_id'] = $targetBankId;
                $needsUpdate = true;
            }

            if ($targetWaba) {
                if (empty($currentWaba) || (!$wabaMatches && $force) || (!$wabaMatches && empty($session->messages()->count()))) {
                    $updates['waba_phone_number_id'] = $targetWaba;
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate) {
                $updatedCount++;
                if (!$isDryRun) {
                    $session->update($updates);
                }

                $tableRows[] = [
                    'session_id' => $session->id,
                    'client_id'  => $client->id,
                    'client_name'=> $client->name,
                    'bank'       => $bank->name,
                    'old_waba'   => $currentWaba ?: '(unset)',
                    'new_waba'   => $updates['waba_phone_number_id'] ?? $currentWaba,
                    'status'     => $isDryRun ? 'Would Update' : 'Updated',
                ];
            }
        }

        if (empty($tableRows)) {
            $this->info('All inspected chat sessions are already aligned with their client bank configuration.');
            return self::SUCCESS;
        }

        $this->table(
            ['Session ID', 'Client ID', 'Client Name', 'Bank', 'Old WABA', 'New WABA', 'Status'],
            $tableRows
        );

        $this->info("Completed. {$updatedCount} session(s) " . ($isDryRun ? 'need updates.' : 'successfully synchronized.'));

        return self::SUCCESS;
    }
}
