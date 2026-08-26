<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\User;
use App\Concerns\HasAuditLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeleteBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasAuditLogging;

    public $timeout = 3600;

    protected int $userId;
    protected string $batchNumber;
    protected array $bankIds;
    protected array $departmentIds;

    public function __construct(int $userId, string $batchNumber, array $bankIds, array $departmentIds)
    {
        $this->userId = $userId;
        $this->batchNumber = $batchNumber;
        $this->bankIds = $bankIds;
        $this->departmentIds = $departmentIds;
    }

    public function handle(): void
    {
        try {
            $query = Client::query()->with('departments')->where('import_batch_number', $this->batchNumber);

            if (!empty($this->bankIds)) {
                $query->whereIn('bank_id', $this->bankIds);
            }

            if (!empty($this->departmentIds)) {
                $query->whereHas('departments', function ($q) {
                    $q->whereIn('departments.id', $this->departmentIds);
                });
            }

            // We use cursor() to handle potentially large datasets without exhausting memory
            $deletedCount = 0;
            
            DB::beginTransaction();
            
            foreach ($query->cursor() as $client) {
                $client->departments()->detach();
                $client->campaigns()->detach();
                $client->delete();
                $deletedCount++;
            }

            DB::commit();

            if ($deletedCount > 0) {
                $this->audit(
                    action: "Deleted {$deletedCount} clients from import batch {$this->batchNumber}",
                    module: 'Clients',
                    meta: [
                        'import_batch_number' => $this->batchNumber,
                        'deleted_count' => $deletedCount,
                        'background_job' => true
                    ],
                    userId: $this->userId
                );
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('DeleteBatchJob failed: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }
}
