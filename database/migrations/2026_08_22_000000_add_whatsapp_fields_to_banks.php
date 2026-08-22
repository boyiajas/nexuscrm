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
        Schema::table('banks', function (Blueprint $table) {
            if (!Schema::hasColumn('banks', 'primary_whatsapp_number')) {
                $table->string('primary_whatsapp_number')->nullable()->after('status');
            }
            if (!Schema::hasColumn('banks', 'secondary_whatsapp_numbers')) {
                $table->json('secondary_whatsapp_numbers')->nullable()->after('primary_whatsapp_number');
            }
            if (!Schema::hasColumn('banks', 'whatsapp_account_id')) {
                $table->unsignedBigInteger('whatsapp_account_id')->nullable()->after('secondary_whatsapp_numbers');
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'whatsapp_account_id')) {
                $table->unsignedBigInteger('whatsapp_account_id')->nullable()->after('secondary_whatsapp_numbers');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            if (Schema::hasColumn('banks', 'whatsapp_account_id')) {
                $table->dropColumn('whatsapp_account_id');
            }
            if (Schema::hasColumn('banks', 'secondary_whatsapp_numbers')) {
                $table->dropColumn('secondary_whatsapp_numbers');
            }
            if (Schema::hasColumn('banks', 'primary_whatsapp_number')) {
                $table->dropColumn('primary_whatsapp_number');
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'whatsapp_account_id')) {
                $table->dropColumn('whatsapp_account_id');
            }
        });
    }
};
