<?php

use App\Services\BankWabaResolver;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            /** @var BankWabaResolver $resolver */
            $resolver = app(BankWabaResolver::class);
            $resolver->syncAndBackfill();
        } catch (\Throwable $e) {
            // Log and allow migration to proceed safely
            \Illuminate\Support\Facades\Log::warning('Migration syncAndBackfill warning: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No destructive rollback needed for data backfilling
    }
};
