<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampaignBankSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_me_includes_all_assigned_banks(): void
    {
        [$tenacity, $capfin] = $this->createBanks();
        $admin = $this->createAdminAssignedToBanks($tenacity, $capfin);

        Sanctum::actingAs($admin);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('bank.id', $tenacity->id)
            ->assertJsonCount(2, 'banks')
            ->assertJsonFragment(['id' => $tenacity->id, 'name' => 'Tenacity'])
            ->assertJsonFragment(['id' => $capfin->id, 'name' => 'Capfin Loans']);
    }

    public function test_non_super_admin_can_create_campaign_for_any_assigned_bank(): void
    {
        [$tenacity, $capfin] = $this->createBanks();
        $admin = $this->createAdminAssignedToBanks($tenacity, $capfin);

        Sanctum::actingAs($admin);

        $this->postJson('/api/campaigns', [
            'name' => 'Capfin campaign',
            'bank_id' => $capfin->id,
            'channels' => ['WhatsApp'],
            'status' => 'Draft',
        ])
            ->assertCreated()
            ->assertJsonPath('bank_id', $capfin->id);

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Capfin campaign',
            'bank_id' => $capfin->id,
        ]);
    }

    public function test_multi_bank_user_must_select_campaign_bank(): void
    {
        [$tenacity, $capfin] = $this->createBanks();
        $admin = $this->createAdminAssignedToBanks($tenacity, $capfin);

        Sanctum::actingAs($admin);

        $this->postJson('/api/campaigns', [
            'name' => 'Ambiguous campaign',
            'channels' => ['WhatsApp'],
            'status' => 'Draft',
        ])
            ->assertUnprocessable()
            ->assertSee('Please select a bank for this campaign.');

        $this->assertDatabaseMissing('campaigns', [
            'name' => 'Ambiguous campaign',
        ]);
    }

    public function test_non_super_admin_cannot_create_campaign_for_unassigned_bank(): void
    {
        [$tenacity, $capfin] = $this->createBanks();
        $otherBank = Bank::query()->create([
            'name' => 'Other Bank',
            'code' => 'other-bank',
            'status' => 'Active',
        ]);
        $admin = $this->createAdminAssignedToBanks($tenacity, $capfin);

        Sanctum::actingAs($admin);

        $this->postJson('/api/campaigns', [
            'name' => 'Other bank campaign',
            'bank_id' => $otherBank->id,
            'channels' => ['WhatsApp'],
            'status' => 'Draft',
        ])->assertForbidden();
    }

    private function createBanks(): array
    {
        return [
            Bank::query()->create([
                'name' => 'Tenacity',
                'code' => 'tenacity',
                'status' => 'Active',
            ]),
            Bank::query()->create([
                'name' => 'Capfin Loans',
                'code' => 'capfin-loans',
                'status' => 'Active',
            ]),
        ];
    }

    private function createAdminAssignedToBanks(Bank $primaryBank, Bank $secondaryBank): User
    {
        $role = Role::query()->where('code', User::ROLE_ADMIN)->firstOrFail();
        $role->permissions()->sync($this->permissionIds([
            'view_campaigns',
            'create_campaigns',
            'edit_campaigns',
        ]));

        $user = User::query()->create([
            'name' => 'Nissa Munsami',
            'email' => 'nissa-' . Str::uuid() . '@example.test',
            'password' => Hash::make('Password123!'),
            'password_changed_at' => now(),
            'password_reset_required' => false,
            'role' => User::ROLE_ADMIN,
            'status' => 'Active',
            'bank_id' => $primaryBank->id,
        ]);

        $user->roles()->sync([$role->id]);
        $user->banks()->sync([$primaryBank->id, $secondaryBank->id]);

        return $user->fresh(['bank', 'banks', 'roles']);
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
