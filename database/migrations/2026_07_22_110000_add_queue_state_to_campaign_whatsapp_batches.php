<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'status')) {
                $table->string('status')->default('Draft')->after('pending');
            }
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'queued_at')) {
                $table->timestamp('queued_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'processing_started_at')) {
                $table->timestamp('processing_started_at')->nullable()->after('queued_at');
            }
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('processing_started_at');
            }
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'paused_at')) {
                $table->timestamp('paused_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'pause_reason')) {
                $table->text('pause_reason')->nullable()->after('paused_at');
            }
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'last_processed_at')) {
                $table->timestamp('last_processed_at')->nullable()->after('pause_reason');
            }
            if (!Schema::hasColumn('campaign_whatsapp_messages', 'messages_per_second')) {
                $table->unsignedInteger('messages_per_second')->default(20)->after('last_processed_at');
            }
        });

        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_whatsapp_recipients', 'queued_at')) {
                $table->timestamp('queued_at')->nullable()->after('provider_display_phone_number');
            }
            if (!Schema::hasColumn('campaign_whatsapp_recipients', 'processing_started_at')) {
                $table->timestamp('processing_started_at')->nullable()->after('queued_at');
            }
            if (!Schema::hasColumn('campaign_whatsapp_recipients', 'last_attempted_at')) {
                $table->timestamp('last_attempted_at')->nullable()->after('processing_started_at');
            }
            if (!Schema::hasColumn('campaign_whatsapp_recipients', 'attempts_count')) {
                $table->unsignedInteger('attempts_count')->default(0)->after('last_attempted_at');
            }
        });

        if (!Schema::hasTable('whatsapp_send_attempts')) {
            Schema::create('whatsapp_send_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_whatsapp_message_id')->nullable()->constrained('campaign_whatsapp_messages')->nullOnDelete();
                $table->foreignId('campaign_whatsapp_recipient_id')->nullable()->constrained('campaign_whatsapp_recipients')->nullOnDelete();
                $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->date('attempt_date')->index();
                $table->timestamp('attempted_at')->index();
                $table->string('status')->nullable();
                $table->string('provider_message_id')->nullable();
                $table->string('error_code')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_send_attempts')) {
            Schema::drop('whatsapp_send_attempts');
        }

        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            $columns = [];
            foreach (['queued_at', 'processing_started_at', 'last_attempted_at', 'attempts_count'] as $column) {
                if (Schema::hasColumn('campaign_whatsapp_recipients', $column)) {
                    $columns[] = $column;
                }
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('campaign_whatsapp_messages', function (Blueprint $table) {
            $columns = [];
            foreach ([
                'status',
                'queued_at',
                'processing_started_at',
                'completed_at',
                'paused_at',
                'pause_reason',
                'last_processed_at',
                'messages_per_second',
            ] as $column) {
                if (Schema::hasColumn('campaign_whatsapp_messages', $column)) {
                    $columns[] = $column;
                }
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
