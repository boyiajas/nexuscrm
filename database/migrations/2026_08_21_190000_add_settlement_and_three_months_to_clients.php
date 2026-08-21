<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'settlement_amount')) {
                $table->decimal('settlement_amount', 15, 2)->nullable()->after('outstanding_balance');
            }
            if (!Schema::hasColumn('clients', 'three_months_amount')) {
                $table->decimal('three_months_amount', 15, 2)->nullable()->after('settlement_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $drops = ['settlement_amount', 'three_months_amount'];
            foreach ($drops as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
