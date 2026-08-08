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
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('app_id');
            $table->text('app_secret');
            $table->text('access_token');
            $table->string('waba_id');
            $table->string('phone_number_id');
            $table->string('display_phone_number');
            $table->text('webhook_verify_token');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
