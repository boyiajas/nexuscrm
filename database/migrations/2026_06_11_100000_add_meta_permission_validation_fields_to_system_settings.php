<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->timestamp('meta_permissions_last_checked_at')->nullable()->after('meta_token_rotation_notes');
            $table->string('meta_permissions_status', 20)->nullable()->after('meta_permissions_last_checked_at');
            $table->json('meta_permissions_snapshot')->nullable()->after('meta_permissions_status');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'meta_permissions_last_checked_at',
                'meta_permissions_status',
                'meta_permissions_snapshot',
            ]);
        });
    }
};
