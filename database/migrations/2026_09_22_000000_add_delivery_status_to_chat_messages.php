<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('provider_message_id', 191)->nullable()->unique();
            $table->string('delivery_status', 20)->nullable();
            $table->timestamp('delivery_status_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropUnique(['provider_message_id']);
            $table->dropColumn(['provider_message_id', 'delivery_status', 'delivery_status_at']);
        });
    }
};
