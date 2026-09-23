<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_flows', function (Blueprint $table) {
            $table->json('initial_expected_replies')->nullable()->after('template_variables');
        });

        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            $table->json('initial_expected_replies')->nullable()->after('flow_definition');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('initial_expected_replies');
        });

        Schema::table('whatsapp_flows', function (Blueprint $table) {
            $table->dropColumn('initial_expected_replies');
        });
    }
};
