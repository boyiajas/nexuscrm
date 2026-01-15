<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'id_number')) {
                $table->string('id_number')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('clients', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('id_number');
            }
            if (!Schema::hasColumn('clients', 'account_number')) {
                $table->string('account_number')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('clients', 'branch_code')) {
                $table->string('branch_code')->nullable()->after('account_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $drops = ['id_number', 'bank_name', 'account_number', 'branch_code'];
            foreach ($drops as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
