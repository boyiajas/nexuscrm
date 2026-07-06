<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_subject_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_type', 50);
            $table->string('status', 50)->default('open');
            $table->string('requester_name')->nullable();
            $table->string('requester_email')->nullable();
            $table->string('requester_phone')->nullable();
            $table->string('received_channel', 50)->nullable();
            $table->text('details');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('complaint_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('complaint_type', 50);
            $table->string('severity', 20)->default('medium');
            $table->string('status', 50)->default('open');
            $table->string('title');
            $table->text('details');
            $table->boolean('escalation_required')->default(false);
            $table->boolean('regulator_notification_required')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('information_officers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('officer_type', 50);
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dataset', 100);
            $table->unsignedInteger('retention_days');
            $table->unsignedInteger('archive_after_days')->nullable();
            $table->unsignedInteger('delete_after_days')->nullable();
            $table->boolean('legal_hold_allowed')->default(true);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('retention_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retention_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dataset', 100);
            $table->string('action_type', 20);
            $table->string('status', 20)->default('pending');
            $table->json('scope_summary')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('execution_result')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_transfer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('protocol', 20)->default('sftp');
            $table->string('environment', 20)->default('production');
            $table->string('status', 20)->default('inactive');
            $table->string('host');
            $table->unsignedInteger('port')->default(22);
            $table->string('username');
            $table->text('password')->nullable();
            $table->text('private_key')->nullable();
            $table->string('remote_path')->nullable();
            $table->string('archive_path')->nullable();
            $table->string('filename_pattern')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_transfer_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transfer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('run_type', 20)->default('sync');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('files_discovered')->default(0);
            $table->unsignedInteger('files_pulled')->default(0);
            $table->unsignedInteger('files_failed')->default(0);
            $table->text('result_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfer_runs');
        Schema::dropIfExists('bank_transfer_profiles');
        Schema::dropIfExists('retention_actions');
        Schema::dropIfExists('retention_policies');
        Schema::dropIfExists('information_officers');
        Schema::dropIfExists('complaint_cases');
        Schema::dropIfExists('data_subject_requests');
    }
};
