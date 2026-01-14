<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            // Per-channel status tracking
            $table->string('whatsapp_status')->nullable(); // Pending, Sent, Delivered, Failed, etc.
            $table->timestamp('whatsapp_sent_at')->nullable();

            $table->string('email_status')->nullable();
            $table->timestamp('email_sent_at')->nullable();

            $table->string('sms_status')->nullable();
            $table->timestamp('sms_sent_at')->nullable();

            $table->timestamps();

            $table->unique(['campaign_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_clients');
    }
};
