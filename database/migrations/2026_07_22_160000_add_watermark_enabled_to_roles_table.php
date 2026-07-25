<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        if (!Schema::hasColumn('roles', 'watermark_enabled')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('watermark_enabled')->default(true)->after('whatsapp_daily_limit');
            });
        }

        DB::table('roles')
            ->whereNull('watermark_enabled')
            ->update(['watermark_enabled' => true]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'watermark_enabled')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('watermark_enabled');
        });
    }
};
