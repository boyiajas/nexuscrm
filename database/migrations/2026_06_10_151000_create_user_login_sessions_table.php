<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_login_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->uuid('session_uuid')->unique();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('authentication_method', 50)->default('password');
            $table->timestamp('authenticated_at');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();
            $table->string('logout_reason', 100)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'logged_out_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_sessions');
    }
};
