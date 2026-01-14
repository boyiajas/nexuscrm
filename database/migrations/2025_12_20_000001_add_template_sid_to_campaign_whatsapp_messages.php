<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'template_sid')) {
                $table->string('template_sid')->nullable()->after('campaign_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_whatsapp_messages', 'template_sid')) {
                $table->dropColumn('template_sid');
            }
        });
    }
};
