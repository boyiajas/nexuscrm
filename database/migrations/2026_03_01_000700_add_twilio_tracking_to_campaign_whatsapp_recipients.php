<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            $table->string('message_sid', 64)->nullable()->after('phone');
            $table->json('status_payload')->nullable()->after('error_message');
            $table->index('message_sid', 'cwr_message_sid_idx');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            $table->dropIndex('cwr_message_sid_idx');
            $table->dropColumn(['message_sid', 'status_payload']);
        });
    }
};
