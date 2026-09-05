<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\ImportUpload;
use App\Concerns\HasAuditLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasAuditLogging;

    public $timeout = 3600;

    public function __construct(
        public string $batchNumber,
        public int $userId
    ) {}

    public function handle(): void
    {
        $upload = ImportUpload::query()->where('import_batch_number', $this->batchNumber)->first();
        
        $query = Client::query()->whereHas('importBatches', function ($q) {
            $q->where('import_batch_number', $this->batchNumber);
        });
        
        $totalToDelete = $query->count();

        if ($totalToDelete === 0) {
            if ($upload) {
                $upload->update(['import_status' => 'deleted']);
            }
            return;
        }

        if ($upload) {
            $upload->update([
                'import_status' => 'deleting',
                'import_summary' => [
                    'total_to_delete' => $totalToDelete,
                    'deleted_rows' => 0,
                ]
            ]);
        }

        $deletedCount = 0;

        // Process in chunks to avoid database locks
        $query->chunkById(100, function ($clients) use (&$deletedCount, $upload, $totalToDelete) {
            foreach ($clients as $client) {
                // Detach from this batch
                $client->importBatches()->where('import_batch_number', $this->batchNumber)->delete();

                if ($client->importBatches()->count() === 0) {
                    $chatSessionIds = DB::table('chat_sessions')->where('client_id', $client->id)->pluck('id');
                    if ($chatSessionIds->isNotEmpty()) {
                        DB::table('chat_messages')->whereIn('chat_session_id', $chatSessionIds)->delete();
                        DB::table('chat_sessions')->whereIn('id', $chatSessionIds)->delete();
                    }

                    $client->departments()->detach();
                    $client->campaigns()->detach();
                    $client->delete();
                    $deletedCount++;
                } else {
                    // Update main import_batch_number to the next most recent one for backward compatibility
                    $nextLatest = $client->importBatches()->orderByDesc('created_at')->first();
                    $client->update(['import_batch_number' => $nextLatest ? $nextLatest->import_batch_number : null]);
                    
                    // We also count this as a "deleted" row from the context of the batch
                    $deletedCount++;
                }
            }

            if ($upload) {
                $upload->update([
                    'import_summary' => [
                        'total_to_delete' => $totalToDelete,
                        'deleted_rows' => $deletedCount,
                    ]
                ]);
            }
        });

        if ($upload) {
            $upload->update(['import_status' => 'deleted']);
        }

        $this->audit(
            action: "Deleted {$deletedCount} clients from import batch {$this->batchNumber}",
            module: 'Clients',
            meta: [
                'import_batch_number' => $this->batchNumber,
                'deleted_count' => $deletedCount,
                'user_id_initiated' => $this->userId,
            ]
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error("DeleteBatchJob failed for batch {$this->batchNumber}: " . $exception->getMessage(), ['exception' => $exception]);
        
        $upload = ImportUpload::query()->where('import_batch_number', $this->batchNumber)->first();
        if ($upload) {
            $upload->update(['import_status' => 'import_failed']);
        }
    }
}
