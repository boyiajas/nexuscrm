<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $perm = DB::table('permissions')->where('code', 'bypass_bank_scoping')->first();
        if (!$perm) {
            return;
        }

        $superAdminRole = DB::table('roles')->where('code', 'SUPER_ADMIN')->first();

        // Delete bypass_bank_scoping for all non-super-admin roles (especially ADMIN)
        $query = DB::table('permission_role')->where('permission_id', $perm->id);
        if ($superAdminRole) {
            $query->where('role_id', '!=', $superAdminRole->id);
        }
        $query->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-super-admin roles should never bypass tenant bank scoping.
    }
};
