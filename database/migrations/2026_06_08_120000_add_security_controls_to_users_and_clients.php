<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->unsignedInteger('failed_login_attempts')->default(0)->after('status');
            }
            if (!Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'whatsapp_opted_out_at')) {
                $table->timestamp('whatsapp_opted_out_at')->nullable()->after('last_contacted_at');
            }
            if (!Schema::hasColumn('clients', 'whatsapp_opt_out_reason')) {
                $table->string('whatsapp_opt_out_reason')->nullable()->after('whatsapp_opted_out_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('users', 'failed_login_attempts')) {
                $drops[] = 'failed_login_attempts';
            }
            if (Schema::hasColumn('users', 'locked_until')) {
                $drops[] = 'locked_until';
            }
            if ($drops) {
                $table->dropColumn($drops);
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('clients', 'whatsapp_opted_out_at')) {
                $drops[] = 'whatsapp_opted_out_at';
            }
            if (Schema::hasColumn('clients', 'whatsapp_opt_out_reason')) {
                $drops[] = 'whatsapp_opt_out_reason';
            }
            if ($drops) {
                $table->dropColumn($drops);
            }
        });
    }
};
