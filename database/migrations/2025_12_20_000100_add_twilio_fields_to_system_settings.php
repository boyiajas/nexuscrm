<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'twilio_auth_token')) {
                $table->string('twilio_auth_token')->nullable()->after('twilio_sid');
            }
            if (!Schema::hasColumn('system_settings', 'twilio_msg_sid')) {
                $table->string('twilio_msg_sid')->nullable()->after('twilio_api_key');
            }
            if (!Schema::hasColumn('system_settings', 'twilio_template_sid')) {
                $table->string('twilio_template_sid')->nullable()->after('twilio_msg_sid');
            }
            if (!Schema::hasColumn('system_settings', 'twilio_whatsapp_from')) {
                $table->string('twilio_whatsapp_from')->nullable()->after('twilio_template_sid');
            }
            if (!Schema::hasColumn('system_settings', 'twilio_status_callback')) {
                $table->string('twilio_status_callback')->nullable()->after('twilio_whatsapp_from');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'twilio_auth_token')) {
                $table->dropColumn('twilio_auth_token');
            }
            if (Schema::hasColumn('system_settings', 'twilio_msg_sid')) {
                $table->dropColumn('twilio_msg_sid');
            }
            if (Schema::hasColumn('system_settings', 'twilio_template_sid')) {
                $table->dropColumn('twilio_template_sid');
            }
            if (Schema::hasColumn('system_settings', 'twilio_whatsapp_from')) {
                $table->dropColumn('twilio_whatsapp_from');
            }
            if (Schema::hasColumn('system_settings', 'twilio_status_callback')) {
                $table->dropColumn('twilio_status_callback');
            }
        });
    }
};
