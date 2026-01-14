<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();

            $table->string('subject')->nullable();
            $table->text('preview_body')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('bounced')->default(0);
            $table->unsignedInteger('opened')->default(0);
            $table->unsignedInteger('clicked')->default(0);

            $table->timestamps();
        });

        Schema::create('campaign_email_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_email_message_id')
                ->constrained('campaign_email_messages')
                ->cascadeOnDelete();

            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->string('email')->nullable();
            $table->string('status')->nullable(); // Sent, Delivered, Bounced, etc.
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_email_recipients');
        Schema::dropIfExists('campaign_email_messages');
    }
};
