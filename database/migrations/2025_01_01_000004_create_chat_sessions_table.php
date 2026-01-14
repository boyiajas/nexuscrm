<?php
// database/migrations/2025_01_01_000004_create_chat_sessions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('agent_id')->nullable()->constrained('users');
            $table->string('client_name');
            $table->string('status')->default('active'); // active | closed
            $table->string('platform')->default('Twilio WhatsApp');
            $table->string('last_message')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained();
            $table->enum('sender', ['user','agent','system']);
            $table->text('content');
            $table->boolean('is_template')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
