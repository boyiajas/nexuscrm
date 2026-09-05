<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_import_batches', function (Blueprint $table) {
            $table->boolean('is_new_client')->default(false)->after('import_batch_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_import_batches', function (Blueprint $table) {
            $table->dropColumn('is_new_client');
        });
    }
};
