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
        Schema::create('campaign_whatsapp_auto_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_whatsapp_message_id')
                ->constrained('campaign_whatsapp_messages')
                ->cascadeOnDelete();
            
            $table->string('trigger_keyword');
            $table->string('template_sid');
            $table->string('template_name')->nullable();
            $table->json('template_variables')->nullable();
            
            $table->timestamps();
            
            // Allow multiple different triggers for the same message to point to different templates
            $table->unique(['campaign_whatsapp_message_id', 'trigger_keyword'], 'idx_msg_trigger');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_whatsapp_auto_replies');
    }
};
