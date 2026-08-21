<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'opt_in')) {
                $table->string('opt_in', 20)->default('none')->after('whatsapp_opt_in_source');
            }
            if (!Schema::hasColumn('clients', 'opt_in_updated_at')) {
                $table->timestamp('opt_in_updated_at')->nullable()->after('opt_in');
            }
        });

        // Populate existing clients: if whatsapp_opted_out_at is set -> 'no', if whatsapp_opted_in_at is set -> 'yes'
        DB::table('clients')
            ->whereNotNull('whatsapp_opted_out_at')
            ->update([
                'opt_in' => 'no',
                'opt_in_updated_at' => DB::raw('whatsapp_opted_out_at'),
            ]);

        DB::table('clients')
            ->whereNull('whatsapp_opted_out_at')
            ->whereNotNull('whatsapp_opted_in_at')
            ->update([
                'opt_in' => 'yes',
                'opt_in_updated_at' => DB::raw('whatsapp_opted_in_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'opt_in_updated_at')) {
                $table->dropColumn('opt_in_updated_at');
            }
            if (Schema::hasColumn('clients', 'opt_in')) {
                $table->dropColumn('opt_in');
            }
        });
    }
};
