<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->string('severity', 20)->default('medium');
            $table->string('status', 30)->default('open');
            $table->string('title');
            $table->text('description');
            $table->string('affected_module', 100)->nullable();
            $table->unsignedInteger('affected_records_count')->nullable();
            $table->boolean('suspected_personal_data_exposed')->default(false);
            $table->boolean('regulator_notification_required')->default(false);
            $table->boolean('bank_notification_required')->default(false);
            $table->timestamp('contained_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('security_incident_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 50);
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incident_events');
        Schema::dropIfExists('security_incidents');
    }
};
