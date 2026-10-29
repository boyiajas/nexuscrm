<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['code' => 'view_analytics_cost'],
            [
                'code' => 'view_analytics_cost',
                'name' => 'View Meta Costs & Spend',
                'module' => 'Security & Audit',
                'description' => 'Access Meta Cloud spend cards, campaign estimated costs, template message rates, and cost telemetry on the Analytics page.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permId = DB::table('permissions')->where('code', 'view_analytics_cost')->value('id');
        if (!$permId) {
            return;
        }

        $roleCodes = ['SUPER_ADMIN', 'ADMIN'];
        $roleIds = DB::table('roles')->whereIn('code', $roleCodes)->pluck('id');

        foreach ($roleIds as $rId) {
            DB::table('permission_role')->updateOrInsert([
                'permission_id' => $permId,
                'role_id' => $rId,
            ], [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $permId = DB::table('permissions')->where('code', 'view_analytics_cost')->value('id');
        if ($permId) {
            DB::table('permission_role')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
};
