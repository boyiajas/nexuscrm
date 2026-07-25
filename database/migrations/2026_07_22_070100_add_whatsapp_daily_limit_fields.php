<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'meta_daily_whatsapp_limit')) {
                $table->unsignedInteger('meta_daily_whatsapp_limit')->nullable()->after('meta_token_rotation_notes');
            }
        });

        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')->nullable()->after('campaign_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_whatsapp_messages', 'created_by_user_id')) {
                $table->dropConstrainedForeignId('created_by_user_id');
            }
        });

        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'meta_daily_whatsapp_limit')) {
                $table->dropColumn('meta_daily_whatsapp_limit');
            }
        });
    }
};
