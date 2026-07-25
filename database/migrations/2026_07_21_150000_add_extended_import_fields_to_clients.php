<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'title')) {
                $table->string('title')->nullable()->after('name');
            }
            if (!Schema::hasColumn('clients', 'initials')) {
                $table->string('initials')->nullable()->after('title');
            }
            if (!Schema::hasColumn('clients', 'first_name')) {
                $table->string('first_name')->nullable()->after('initials');
            }
            if (!Schema::hasColumn('clients', 'surname')) {
                $table->string('surname')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('clients', 'easy_pay_number')) {
                $table->string('easy_pay_number')->nullable()->after('account_number');
            }
            if (!Schema::hasColumn('clients', 'cell_phone')) {
                $table->string('cell_phone')->nullable()->after('easy_pay_number');
            }
            if (!Schema::hasColumn('clients', 'home_phone')) {
                $table->string('home_phone')->nullable()->after('cell_phone');
            }
            if (!Schema::hasColumn('clients', 'work_phone')) {
                $table->string('work_phone')->nullable()->after('home_phone');
            }
            if (!Schema::hasColumn('clients', 'arrears_amount')) {
                $table->decimal('arrears_amount', 15, 2)->nullable()->after('work_phone');
            }
            if (!Schema::hasColumn('clients', 'outstanding_balance')) {
                $table->decimal('outstanding_balance', 15, 2)->nullable()->after('arrears_amount');
            }
            if (!Schema::hasColumn('clients', 'installment_amount')) {
                $table->decimal('installment_amount', 15, 2)->nullable()->after('outstanding_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $drops = [
                'title',
                'initials',
                'first_name',
                'surname',
                'easy_pay_number',
                'cell_phone',
                'home_phone',
                'work_phone',
                'arrears_amount',
                'outstanding_balance',
                'installment_amount',
            ];

            foreach ($drops as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
