<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_number_controls', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number_id')->unique();
            $table->string('display_phone_number')->nullable();
            $table->string('normalized_display_phone_number')->nullable()->index();
            $table->string('quality_rating', 30)->nullable();
            $table->boolean('is_paused')->default(false)->index();
            $table->text('pause_reason')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->foreignId('paused_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_number_controls');
    }
};
