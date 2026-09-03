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
            $table->boolean('live_chat_locked')->default(false)->after('app_name');
            $table->string('live_chat_locked_message')->nullable()->default('Live chat is temporarily disabled.')->after('live_chat_locked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['live_chat_locked', 'live_chat_locked_message']);
        });
    }
};
