<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('meta_environment', 20)->nullable()->after('whatsapp_provider');
            $table->timestamp('meta_token_last_rotated_at')->nullable()->after('meta_webhook_verify_token');
            $table->timestamp('meta_token_expires_at')->nullable()->after('meta_token_last_rotated_at');
            $table->text('meta_token_rotation_notes')->nullable()->after('meta_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'meta_environment',
                'meta_token_last_rotated_at',
                'meta_token_expires_at',
                'meta_token_rotation_notes',
            ]);
        });
    }
};
