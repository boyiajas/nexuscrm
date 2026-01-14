<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();

            $table->text('text')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('pending')->default(0);

            $table->timestamps();
        });

        Schema::create('campaign_sms_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_sms_message_id')
                ->constrained('campaign_sms_messages')
                ->cascadeOnDelete();

            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->string('phone')->nullable();
            $table->string('status')->nullable(); // Pending, Sent, Delivered, Failed
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_sms_recipients');
        Schema::dropIfExists('campaign_sms_messages');
    }
};
