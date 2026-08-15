<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolePermissionRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_permissions_do_not_continue_to_work_via_legacy_role_shortcuts(): void
    {
        $managerRole = Role::query()->where('code', User::ROLE_MANAGER)->firstOrFail();
        $managerRole->permissions()->sync($this->permissionIds(['view_campaigns']));

        $manager = $this->makeUserForRole($managerRole, User::ROLE_MANAGER);

        $this->assertTrue($manager->canViewCampaigns());
        $this->assertFalse($manager->canViewSecurityIncidents());
        $this->assertFalse($manager->canManageSecurityIncidents());
        $this->assertFalse($manager->canViewComplianceConsole());
        $this->assertFalse($manager->canManageComplianceConsole());
        $this->assertFalse($manager->canRequestSensitiveExports());
        $this->assertFalse($manager->canApproveExportRequests());
        $this->assertFalse($manager->canBypassExportApproval());

        $agentRole = Role::query()->where('code', User::ROLE_AGENT)->firstOrFail();
        $agentRole->permissions()->sync($this->permissionIds(['view_clients']));

        $agent = $this->makeUserForRole($agentRole, User::ROLE_AGENT);

        $this->assertFalse($agent->isPortfolioScoped());
    }

    public function test_added_permissions_unlock_the_specific_api_capabilities_they_enable(): void
    {
        Sanctum::actingAs($this->makeUserForRole(
            $this->createCustomRole('SECURITY_MANAGER_CUSTOM', ['manage_security_incidents'])
        ));
        $this->getJson('/api/security-incidents')->assertOk();

        Sanctum::actingAs($this->makeUserForRole(
            $this->createCustomRole('COMPLIANCE_MANAGER_CUSTOM', ['manage_compliance_console'])
        ));
        $this->getJson('/api/compliance/overview')->assertOk();

        Sanctum::actingAs($this->makeUserForRole(
            $this->createCustomRole('EXPORT_APPROVER_CUSTOM', ['approve_exports'])
        ));
        $this->getJson('/api/export-requests')->assertOk();

        Sanctum::actingAs($this->makeUserForRole(
            $this->createCustomRole('CHAT_VIEWER_CUSTOM', ['view_live_chat'])
        ));
        $this->getJson('/api/chat/sessions')->assertOk();

        Sanctum::actingAs($this->makeUserForRole(
            $this->createCustomRole('FLOW_MANAGER_CUSTOM', ['manage_whatsapp_flows'])
        ));
        $this->getJson('/api/whatsapp-flows')->assertOk();
    }

    public function test_endpoints_use_their_own_permissions_instead_of_shared_management_shortcuts(): void
    {
        Sanctum::actingAs($this->makeUserForRole(
            $this->createCustomRole('ROLES_ONLY_CUSTOM', ['manage_roles'])
        ));
        $this->getJson('/api/roles')->assertOk();
        $this->getJson('/api/users')->assertForbidden();

        Sanctum::actingAs($this->makeUserForRole(
            $this->createCustomRole('USERS_ONLY_CUSTOM', ['manage_users'])
        ));
        $this->getJson('/api/users')->assertOk();
        $this->getJson('/api/roles')->assertForbidden();

        Sanctum::actingAs($this->makeUserForRole(
            $this->createCustomRole('CAMPAIGNS_ONLY_CUSTOM', ['view_campaigns'])
        ));
        $this->getJson('/api/campaigns')->assertOk();
        $this->getJson('/api/clients')->assertForbidden();
        $this->getJson('/api/import-uploads')->assertForbidden();
        $this->getJson('/api/chat/sessions')->assertForbidden();
        $this->getJson('/api/whatsapp-flows')->assertForbidden();
    }

    private function createCustomRole(string $code, array $permissionCodes): Role
    {
        $role = Role::query()->create([
            'code' => $code,
            'name' => str_replace('_', ' ', $code),
            'description' => 'Test role',
            'whatsapp_daily_limit' => 500,
            'watermark_enabled' => true,
            'is_system' => false,
            'is_active' => true,
        ]);

        $role->permissions()->sync($this->permissionIds($permissionCodes));

        return $role->fresh();
    }

    private function makeUserForRole(Role $role, ?string $primaryRole = null): User
    {
        $user = User::query()->create([
            'name' => 'Test User ' . $role->code,
            'email' => Str::lower($role->code) . '-' . Str::uuid() . '@example.test',
            'password' => Hash::make('Password123!'),
            'password_changed_at' => now(),
            'password_reset_required' => false,
            'role' => $primaryRole ?: $role->code,
            'status' => 'Active',
        ]);

        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    private function permissionIds(array $permissionCodes): array
    {
        $ids = Permission::query()
            ->whereIn('code', $permissionCodes)
            ->pluck('id')
            ->all();

        $this->assertCount(count($permissionCodes), $ids, 'One or more expected permissions were not seeded.');

        return $ids;
    }
}
