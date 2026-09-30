<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientStoreNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_be_created_and_updated_with_a_store_name(): void
    {
        $bank = Bank::query()->create([
            'name' => 'Store Bank',
            'code' => 'store-bank',
            'status' => 'Active',
        ]);
        $department = Department::query()->create([
            'name' => 'Store Collections',
            'code' => 'store-collections',
            'status' => 'Active',
            'bank_id' => $bank->id,
        ]);
        $user = User::factory()->create([
            'bank_id' => $bank->id,
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);
        $user->roles()->sync([
            Role::query()->where('code', User::ROLE_SUPER_ADMIN)->firstOrFail()->id,
        ]);

        Sanctum::actingAs($user->fresh('roles'));

        $clientId = $this->postJson('/api/clients', [
            'name' => 'Store Client',
            'bank_id' => $bank->id,
            'department_ids' => [$department->id],
            'store_name' => 'Ackermans Cape Town',
            'activation_amount' => 250.50,
            'ptp_due_date' => '2026-10-31',
            'ptp_amount' => 500.75,
        ])
            ->assertCreated()
            ->assertJsonPath('store_name', 'Ackermans Cape Town')
            ->assertJsonPath('activation_amount', '250.50')
            ->assertJsonPath('ptp_due_date', '2026-10-31')
            ->assertJsonPath('ptp_amount', '500.75')
            ->json('id');

        $this->putJson("/api/clients/{$clientId}", [
            'store_name' => 'Ackermans Bellville',
            'activation_amount' => 300,
            'ptp_due_date' => '2026-11-15',
            'ptp_amount' => 600,
        ])
            ->assertOk()
            ->assertJsonPath('store_name', 'Ackermans Bellville')
            ->assertJsonPath('activation_amount', '300.00')
            ->assertJsonPath('ptp_due_date', '2026-11-15')
            ->assertJsonPath('ptp_amount', '600.00');

        $this->assertDatabaseHas('clients', [
            'id' => $clientId,
            'store_name' => 'Ackermans Bellville',
            'activation_amount' => 300,
            'ptp_due_date' => '2026-11-15',
            'ptp_amount' => 600,
        ]);
    }
}
