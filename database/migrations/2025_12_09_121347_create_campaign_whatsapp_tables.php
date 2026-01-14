<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();

            $table->string('template_name')->nullable();   // Twilio/Meta template
            $table->string('name')->nullable();            // Optional label
            $table->text('preview_body')->nullable();      // snapshot of content
            $table->timestamp('sent_at')->nullable();

            // Aggregate stats
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('pending')->default(0);

            $table->timestamps();
        });

        Schema::create('campaign_whatsapp_recipients', function (Blueprint $table) {
            $table->id();
            // use shorter column name to avoid long FK identifier
            $table->unsignedBigInteger('whatsapp_message_id');
            $table->foreign('whatsapp_message_id', 'fk_cwa_msg_rec')
                ->references('id')
                ->on('campaign_whatsapp_messages')
                ->onDelete('cascade');
            
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->string('phone')->nullable();
            $table->string('status')->nullable(); // Pending, Sent, Delivered, Failed
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Optional: latest reply from client
            $table->text('last_response')->nullable();
            $table->timestamp('last_response_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_whatsapp_recipients');
        Schema::dropIfExists('campaign_whatsapp_messages');
    }
};
