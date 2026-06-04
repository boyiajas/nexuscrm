<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'whatsapp_provider')) {
                $table->string('whatsapp_provider')->nullable()->after('app_logo_path');
            }
            if (!Schema::hasColumn('system_settings', 'meta_app_id')) {
                $table->string('meta_app_id')->nullable()->after('whatsapp_provider');
            }
            if (!Schema::hasColumn('system_settings', 'meta_app_secret')) {
                $table->string('meta_app_secret')->nullable()->after('meta_app_id');
            }
            if (!Schema::hasColumn('system_settings', 'meta_access_token')) {
                $table->text('meta_access_token')->nullable()->after('meta_app_secret');
            }
            if (!Schema::hasColumn('system_settings', 'meta_whatsapp_business_account_id')) {
                $table->string('meta_whatsapp_business_account_id')->nullable()->after('meta_access_token');
            }
            if (!Schema::hasColumn('system_settings', 'meta_whatsapp_phone_number_id')) {
                $table->string('meta_whatsapp_phone_number_id')->nullable()->after('meta_whatsapp_business_account_id');
            }
            if (!Schema::hasColumn('system_settings', 'meta_whatsapp_display_phone_number')) {
                $table->string('meta_whatsapp_display_phone_number')->nullable()->after('meta_whatsapp_phone_number_id');
            }
            if (!Schema::hasColumn('system_settings', 'meta_webhook_verify_token')) {
                $table->string('meta_webhook_verify_token')->nullable()->after('meta_whatsapp_display_phone_number');
            }
        });

        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_whatsapp_recipients', 'provider_message_id')) {
                $table->string('provider_message_id', 128)->nullable()->after('message_sid');
                $table->index('provider_message_id', 'cwr_provider_message_id_idx');
            }
            if (!Schema::hasColumn('campaign_whatsapp_recipients', 'provider_status_payload')) {
                $table->json('provider_status_payload')->nullable()->after('status_payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_whatsapp_recipients', 'provider_message_id')) {
                $table->dropIndex('cwr_provider_message_id_idx');
                $table->dropColumn('provider_message_id');
            }
            if (Schema::hasColumn('campaign_whatsapp_recipients', 'provider_status_payload')) {
                $table->dropColumn('provider_status_payload');
            }
        });

        Schema::table('system_settings', function (Blueprint $table) {
            foreach ([
                'whatsapp_provider',
                'meta_app_id',
                'meta_app_secret',
                'meta_access_token',
                'meta_whatsapp_business_account_id',
                'meta_whatsapp_phone_number_id',
                'meta_whatsapp_display_phone_number',
                'meta_webhook_verify_token',
            ] as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
