<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'app_name')) {
                $table->string('app_name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('system_settings', 'app_short_name')) {
                $table->string('app_short_name')->nullable()->after('app_name');
            }
            if (!Schema::hasColumn('system_settings', 'app_tagline')) {
                $table->string('app_tagline')->nullable()->after('app_short_name');
            }
            if (!Schema::hasColumn('system_settings', 'company_name')) {
                $table->string('company_name')->nullable()->after('app_tagline');
            }
            if (!Schema::hasColumn('system_settings', 'support_email')) {
                $table->string('support_email')->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('system_settings', 'support_phone')) {
                $table->string('support_phone')->nullable()->after('support_email');
            }
            if (!Schema::hasColumn('system_settings', 'app_logo_path')) {
                $table->string('app_logo_path')->nullable()->after('support_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            foreach ([
                'app_name',
                'app_short_name',
                'app_tagline',
                'company_name',
                'support_email',
                'support_phone',
                'app_logo_path',
            ] as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
