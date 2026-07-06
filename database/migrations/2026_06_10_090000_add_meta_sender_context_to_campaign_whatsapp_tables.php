<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            $table->string('provider_phone_number_id')->nullable()->after('template_name');
            $table->string('provider_display_phone_number')->nullable()->after('provider_phone_number_id');
        });

        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            $table->string('provider_phone_number_id')->nullable()->after('provider_message_id');
            $table->string('provider_display_phone_number')->nullable()->after('provider_phone_number_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            $table->dropColumn(['provider_phone_number_id', 'provider_display_phone_number']);
        });

        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['provider_phone_number_id', 'provider_display_phone_number']);
        });
    }
};
