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
            if (!Schema::hasColumn('clients', 'whatsapp_contact_basis')) {
                $table->string('whatsapp_contact_basis')->nullable()->after('whatsapp_opt_out_reason');
            }
            if (!Schema::hasColumn('clients', 'whatsapp_contact_basis_details')) {
                $table->text('whatsapp_contact_basis_details')->nullable()->after('whatsapp_contact_basis');
            }
            if (!Schema::hasColumn('clients', 'whatsapp_opted_in_at')) {
                $table->timestamp('whatsapp_opted_in_at')->nullable()->after('whatsapp_contact_basis_details');
            }
            if (!Schema::hasColumn('clients', 'whatsapp_opt_in_source')) {
                $table->string('whatsapp_opt_in_source')->nullable()->after('whatsapp_opted_in_at');
            }
        });

        DB::table('clients')
            ->whereNull('whatsapp_opted_out_at')
            ->whereNull('whatsapp_contact_basis')
            ->update([
                'whatsapp_contact_basis' => 'bank_instruction',
                'whatsapp_opt_in_source' => 'legacy_migration',
            ]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach ([
                'whatsapp_contact_basis',
                'whatsapp_contact_basis_details',
                'whatsapp_opted_in_at',
                'whatsapp_opt_in_source',
            ] as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
