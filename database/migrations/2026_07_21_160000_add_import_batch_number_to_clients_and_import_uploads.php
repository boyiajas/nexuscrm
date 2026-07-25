<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_uploads', function (Blueprint $table) {
            if (!Schema::hasColumn('import_uploads', 'import_batch_number')) {
                $table->string('import_batch_number', 60)->nullable()->after('original_filename')->index();
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'import_batch_number')) {
                $table->string('import_batch_number', 60)->nullable()->after('installment_amount')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'import_batch_number')) {
                $table->dropColumn('import_batch_number');
            }
        });

        Schema::table('import_uploads', function (Blueprint $table) {
            if (Schema::hasColumn('import_uploads', 'import_batch_number')) {
                $table->dropColumn('import_batch_number');
            }
        });
    }
};
