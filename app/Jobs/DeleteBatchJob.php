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
        
        $query = Client::query()->where('import_batch_number', $this->batchNumber);
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
            $clientIds = $clients->pluck('id');

            $chatSessionIds = DB::table('chat_sessions')->whereIn('client_id', $clientIds)->pluck('id');
            if ($chatSessionIds->isNotEmpty()) {
                DB::table('chat_messages')->whereIn('chat_session_id', $chatSessionIds)->delete();
                DB::table('chat_sessions')->whereIn('id', $chatSessionIds)->delete();
            }

            foreach ($clients as $client) {
                $client->departments()->detach();
                $client->campaigns()->detach();
                $client->delete();
                $deletedCount++;
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
