<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('activation_amount', 15, 2)->nullable()->after('installment_amount');
            $table->date('ptp_due_date')->nullable()->after('activation_amount');
            $table->decimal('ptp_amount', 15, 2)->nullable()->after('ptp_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['activation_amount', 'ptp_due_date', 'ptp_amount']);
        });
    }
};
