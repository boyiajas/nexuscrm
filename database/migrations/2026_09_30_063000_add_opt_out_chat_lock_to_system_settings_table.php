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
        Schema::table('system_settings', function (Blueprint $table) {
            $table->boolean('disable_chat_for_opted_out_clients')->default(true)->after('live_chat_locked_message');
            $table->string('opted_out_chat_message')->nullable()->default('This client has opted out of WhatsApp communication. Messaging is disabled.')->after('disable_chat_for_opted_out_clients');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['disable_chat_for_opted_out_clients', 'opted_out_chat_message']);
        });
    }
};
