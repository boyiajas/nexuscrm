<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'enable_live_chat')) {
                $table->boolean('enable_live_chat')->default(false)->after('pending');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_whatsapp_messages', 'enable_live_chat')) {
                $table->dropColumn('enable_live_chat');
            }
        });
    }
};
