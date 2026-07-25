<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('status');
        });

        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            $table->string('current_flow_step_id')->nullable()->after('last_response_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });

        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            $table->dropColumn('current_flow_step_id');
        });
    }
};
