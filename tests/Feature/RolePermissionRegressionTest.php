<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

        Sanctum::actingAs($this->makeUserForRole(
            $this->createCustomRole('PAUSE_WHATSAPP_NUMBERS_CUSTOM', ['pause_whatsapp_numbers'])
        ));
        $this->patchJson('/api/settings/meta/phone-numbers/1247262038476724/pause', [
            'paused' => true,
        ])->assertOk();

        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['data' => []], 200),
        ]);
        Sanctum::actingAs($this->makeUserForRole(
            $this->createCustomRole('META_BILLING_CUSTOM', ['view_meta_billing'])
        ));
        $this->getJson('/api/meta-billing')->assertOk();

        Sanctum::actingAs($this->makeUserForRole(
            $this->createCustomRole('ANALYTICS_VIEWER_CUSTOM', ['view_analytics'])
        ));
        $this->getJson('/api/analytics')->assertOk();
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
        $this->getJson('/api/meta-billing')->assertForbidden();
        $this->getJson('/api/analytics')->assertForbidden();

        Sanctum::actingAs($this->makeUserForRole(
            $this->createCustomRole('WABA_NUMBERS_WITHOUT_PAUSE_CUSTOM', ['settings_waba_numbers'])
        ));
        $this->patchJson('/api/settings/meta/phone-numbers/1247262038476724/pause', [
            'paused' => true,
        ])->assertForbidden();
    }

    public function test_meta_billing_permission_can_be_checked_and_unchecked_on_role(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['data' => []], 200),
        ]);

        $admin = $this->makeUserForRole(
            $this->createCustomRole('ADMIN_FOR_PERMS', ['manage_roles'])
        );
        Sanctum::actingAs($admin);

        $permissionsResponse = $this->getJson('/api/permissions')->assertOk();
        $metaBillingPerm = collect($permissionsResponse->json())->firstWhere('code', 'view_meta_billing');
        $this->assertNotNull($metaBillingPerm);
        $this->assertSame('Meta Billing', $metaBillingPerm['name']);
        $this->assertSame('Security & Audit', $metaBillingPerm['module']);

        $role = $this->createCustomRole('AUDITOR_CUSTOM', []);

        // Check permission (add view_meta_billing)
        $this->putJson("/api/roles/{$role->id}", [
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'whatsapp_daily_limit' => $role->whatsapp_daily_limit,
            'watermark_enabled' => true,
            'is_active' => true,
            'permissions' => ['view_meta_billing'],
        ])->assertOk();

        $this->assertTrue($role->fresh()->permissions()->where('code', 'view_meta_billing')->exists());

        // Acting as user with this role can access meta billing
        Sanctum::actingAs($this->makeUserForRole($role->fresh()));
        $this->getJson('/api/meta-billing')->assertOk();

        // Uncheck permission (remove view_meta_billing)
        Sanctum::actingAs($admin);
        $this->putJson("/api/roles/{$role->id}", [
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'whatsapp_daily_limit' => $role->whatsapp_daily_limit,
            'watermark_enabled' => true,
            'is_active' => true,
            'permissions' => [],
        ])->assertOk();

        $this->assertFalse($role->fresh()->permissions()->where('code', 'view_meta_billing')->exists());

        // Acting as user with this updated role is now forbidden
        Sanctum::actingAs($this->makeUserForRole($role->fresh()));
        $this->getJson('/api/meta-billing')->assertForbidden();
    }

    public function test_analytics_permission_can_be_checked_and_unchecked_on_role(): void
    {
        $admin = $this->makeUserForRole(
            $this->createCustomRole('ADMIN_FOR_PERMS_ANALYTICS', ['manage_roles'])
        );
        Sanctum::actingAs($admin);

        $permissionsResponse = $this->getJson('/api/permissions')->assertOk();
        $analyticsPerm = collect($permissionsResponse->json())->firstWhere('code', 'view_analytics');
        $this->assertNotNull($analyticsPerm);
        $this->assertSame('Analytics / Reports', $analyticsPerm['name']);
        $this->assertSame('Security & Audit', $analyticsPerm['module']);

        $role = $this->createCustomRole('REPORTS_ANALYST_CUSTOM', []);

        // Check permission (add view_analytics)
        $this->putJson("/api/roles/{$role->id}", [
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'whatsapp_daily_limit' => $role->whatsapp_daily_limit,
            'watermark_enabled' => true,
            'is_active' => true,
            'permissions' => ['view_analytics'],
        ])->assertOk();

        $this->assertTrue($role->fresh()->permissions()->where('code', 'view_analytics')->exists());

        // Acting as user with this role can access analytics
        Sanctum::actingAs($this->makeUserForRole($role->fresh()));
        $this->getJson('/api/analytics')->assertOk();

        // Uncheck permission (remove view_analytics)
        Sanctum::actingAs($admin);
        $this->putJson("/api/roles/{$role->id}", [
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'whatsapp_daily_limit' => $role->whatsapp_daily_limit,
            'watermark_enabled' => true,
            'is_active' => true,
            'permissions' => [],
        ])->assertOk();

        $this->assertFalse($role->fresh()->permissions()->where('code', 'view_analytics')->exists());

        // Acting as user with this updated role is now forbidden
        Sanctum::actingAs($this->makeUserForRole($role->fresh()));
        $this->getJson('/api/analytics')->assertForbidden();
    }

    public function test_view_analytics_cost_permission_can_be_checked_and_unchecked_on_role(): void
    {
        $admin = $this->makeUserForRole(
            Role::query()->where('code', User::ROLE_SUPER_ADMIN)->firstOrFail(),
            User::ROLE_SUPER_ADMIN
        );

        $role = Role::query()->create([
            'code' => 'ANALYTICS_TEST_ROLE',
            'name' => 'Analytics Test Role',
            'description' => 'Test role for analytics cost toggle',
            'whatsapp_daily_limit' => 100,
            'watermark_enabled' => true,
            'is_system' => false,
            'is_active' => true,
        ]);

        // 1. Check permissions (view_analytics AND view_analytics_cost)
        Sanctum::actingAs($admin);
        $this->putJson("/api/roles/{$role->id}", [
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'whatsapp_daily_limit' => $role->whatsapp_daily_limit,
            'watermark_enabled' => true,
            'is_active' => true,
            'permissions' => ['view_analytics', 'view_analytics_cost'],
        ])->assertOk();

        $this->assertTrue($role->fresh()->permissions()->where('code', 'view_analytics')->exists());
        $this->assertTrue($role->fresh()->permissions()->where('code', 'view_analytics_cost')->exists());

        // Acting as user with role having cost permission: can_view_cost is true
        $user = $this->makeUserForRole($role->fresh());
        Sanctum::actingAs($user);
        $res = $this->getJson('/api/analytics')->assertOk();
        $this->assertTrue($res->json('can_view_cost'));

        // 2. Uncheck permission (remove view_analytics_cost, keep view_analytics)
        Sanctum::actingAs($admin);
        $this->putJson("/api/roles/{$role->id}", [
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'whatsapp_daily_limit' => $role->whatsapp_daily_limit,
            'watermark_enabled' => true,
            'is_active' => true,
            'permissions' => ['view_analytics'],
        ])->assertOk();

        $this->assertTrue($role->fresh()->permissions()->where('code', 'view_analytics')->exists());
        $this->assertFalse($role->fresh()->permissions()->where('code', 'view_analytics_cost')->exists());

        // Acting as user with role where cost permission is removed:
        // User can still access analytics, but cost is redacted and can_view_cost is false
        Sanctum::actingAs($user->fresh());
        $resWithoutCost = $this->getJson('/api/analytics')->assertOk();
        $this->assertFalse($resWithoutCost->json('can_view_cost'));
        $this->assertNull($resWithoutCost->json('spend.total'));
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
