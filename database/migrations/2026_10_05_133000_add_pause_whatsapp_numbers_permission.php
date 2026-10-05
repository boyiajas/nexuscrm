<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['code' => 'pause_whatsapp_numbers'],
            [
                'code' => 'pause_whatsapp_numbers',
                'name' => 'Pause / Resume WhatsApp Numbers',
                'module' => 'Account & Settings',
                'description' => 'Pause and resume registered WhatsApp phone numbers from sending campaigns.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permId = DB::table('permissions')->where('code', 'pause_whatsapp_numbers')->value('id');
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
        $permId = DB::table('permissions')->where('code', 'pause_whatsapp_numbers')->value('id');
        if ($permId) {
            DB::table('permission_role')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
};
