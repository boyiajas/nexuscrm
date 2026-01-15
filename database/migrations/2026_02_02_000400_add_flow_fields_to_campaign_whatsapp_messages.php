<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'mode')) {
                $table->string('mode')->default('template')->after('campaign_id');
            }
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'whatsapp_flow_id')) {
                $table->unsignedBigInteger('whatsapp_flow_id')->nullable()->after('template_sid');
            }
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'flow_name')) {
                $table->string('flow_name')->nullable()->after('whatsapp_flow_id');
            }
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'flow_definition')) {
                $table->json('flow_definition')->nullable()->after('flow_name');
            }
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'track_responses')) {
                $table->boolean('track_responses')->default(false)->after('pending');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_whatsapp_messages', 'mode')) {
                $table->dropColumn('mode');
            }
            if (Schema::hasColumn('campaign_whatsapp_messages', 'whatsapp_flow_id')) {
                $table->dropColumn('whatsapp_flow_id');
            }
            if (Schema::hasColumn('campaign_whatsapp_messages', 'flow_name')) {
                $table->dropColumn('flow_name');
            }
            if (Schema::hasColumn('campaign_whatsapp_messages', 'flow_definition')) {
                $table->dropColumn('flow_definition');
            }
            if (Schema::hasColumn('campaign_whatsapp_messages', 'track_responses')) {
                $table->dropColumn('track_responses');
            }
        });
    }
};
