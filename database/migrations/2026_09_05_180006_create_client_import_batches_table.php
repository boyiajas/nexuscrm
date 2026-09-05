<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('client_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('import_batch_number')->index();
            $table->timestamps();
            $table->unique(['client_id', 'import_batch_number']);
        });

        // Seed existing data
        DB::statement("
            INSERT INTO client_import_batches (client_id, import_batch_number, created_at, updated_at)
            SELECT id, import_batch_number, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM clients
            WHERE import_batch_number IS NOT NULL AND import_batch_number != ''
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_import_batches');
    }
};
