<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'password_reset_required')) {
                $table->boolean('password_reset_required')->default(false)->after('password_changed_at');
            }
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 64)->nullable()->after('last_login_at');
            }
            if (!Schema::hasColumn('users', 'last_login_user_agent')) {
                $table->text('last_login_user_agent')->nullable()->after('last_login_ip');
            }
            if (!Schema::hasColumn('users', 'deactivated_at')) {
                $table->timestamp('deactivated_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('users', 'deactivated_by_user_id')) {
                $table->foreignId('deactivated_by_user_id')->nullable()->after('deactivated_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'admin_ip_allowlist')) {
                $table->text('admin_ip_allowlist')->nullable()->after('support_phone');
            }
            if (!Schema::hasColumn('system_settings', 'password_max_age_days')) {
                $table->unsignedInteger('password_max_age_days')->nullable()->after('admin_ip_allowlist');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deactivated_by_user_id')) {
                $table->dropConstrainedForeignId('deactivated_by_user_id');
            }
            foreach ([
                'password_changed_at',
                'password_reset_required',
                'last_login_ip',
                'last_login_user_agent',
                'deactivated_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('system_settings', function (Blueprint $table) {
            foreach (['admin_ip_allowlist', 'password_max_age_days'] as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
