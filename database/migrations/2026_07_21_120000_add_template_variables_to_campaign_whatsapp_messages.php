<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'template_variables')) {
                $table->json('template_variables')->nullable()->after('preview_body');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_whatsapp_messages', 'template_variables')) {
                $table->dropColumn('template_variables');
            }
        });
    }
};
