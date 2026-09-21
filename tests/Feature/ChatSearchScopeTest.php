<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\ChatSession;
use App\Models\Client;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatSearchScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_both_reply_clients_and_system_clients(): void
    {
        $bank = Bank::query()->create(['name' => 'Bank Alpha', 'code' => 'ALPHA', 'status' => 'Active']);
        $dept = Department::query()->create(['name' => 'Support', 'code' => 'SUPP']);

        $user = $this->createChatUser($bank, [$dept], User::ROLE_ADMIN);

        // 1. Client with active chat session ("Reply Client")
        $replyClient = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Alice Reply',
            'phone' => '+27821111111',
            'bank_name' => $bank->name,
        ]);
        $replyClient->departments()->sync([$dept->id]);

        $session = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $replyClient->id,
            'client_name' => 'Alice Reply',
            'phone' => '+27821111111',
            'status' => 'active',
            'platform' => 'whatsapp',
            'waba_phone_number_id' => 'waba-1',
            'last_message' => 'Thank you for replying',
            'unread_count' => 0,
        ]);

        // 2. Client on system without any chat session
        $systemClient = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Alice System',
            'phone' => '+27822222222',
            'bank_name' => $bank->name,
        ]);
        $systemClient->departments()->sync([$dept->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/chat/sessions?search=Alice')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // First item is the existing session
        $this->assertEquals($session->id, $data[0]['id']);
        $this->assertEquals('Alice Reply', $data[0]['client_name']);
        $this->assertFalse(isset($data[0]['is_client_only']) && $data[0]['is_client_only']);

        // Second item is the system client
        $this->assertEquals('client_' . $systemClient->id, $data[1]['id']);
        $this->assertEquals('Alice System', $data[1]['client_name']);
        $this->assertTrue($data[1]['is_client_only']);
        $this->assertEquals($systemClient->id, $data[1]['client_id']);
    }

    public function test_search_excludes_clients_from_other_banks(): void
    {
        $bankA = Bank::query()->create(['name' => 'Bank Alpha', 'code' => 'ALPHA', 'status' => 'Active']);
        $bankB = Bank::query()->create(['name' => 'Bank Beta', 'code' => 'BETA', 'status' => 'Active']);
        $dept = Department::query()->create(['name' => 'Support', 'code' => 'SUPP']);

        $user = $this->createChatUser($bankA, [$dept], User::ROLE_ADMIN);

        // Client in user's bank
        $clientA = Client::query()->create([
            'bank_id' => $bankA->id,
            'name' => 'Smith Alpha',
            'phone' => '+27823333333',
            'bank_name' => $bankA->name,
        ]);
        $clientA->departments()->sync([$dept->id]);

        // Client in another bank
        $clientB = Client::query()->create([
            'bank_id' => $bankB->id,
            'name' => 'Smith Beta',
            'phone' => '+27824444444',
            'bank_name' => $bankB->name,
        ]);
        $clientB->departments()->sync([$dept->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/chat/sessions?search=Smith')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Smith Alpha', $data[0]['client_name']);
    }

    public function test_search_excludes_clients_from_other_departments_for_admin_and_agent_users(): void
    {
        $bank = Bank::query()->create(['name' => 'Bank Alpha', 'code' => 'ALPHA', 'status' => 'Active']);
        $deptSupport = Department::query()->create(['name' => 'Support', 'code' => 'SUPP']);
        $deptSales = Department::query()->create(['name' => 'Sales', 'code' => 'SALES']);

        // Client in Support
        $clientSupport = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Johnson Support',
            'phone' => '+27825555555',
            'bank_name' => $bank->name,
        ]);
        $clientSupport->departments()->sync([$deptSupport->id]);

        // Client in Sales only
        $clientSales = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Johnson Sales',
            'phone' => '+27826666666',
            'bank_name' => $bank->name,
        ]);
        $clientSales->departments()->sync([$deptSales->id]);

        // 1. Admin assigned to Support only
        $admin = $this->createChatUser($bank, [$deptSupport], User::ROLE_ADMIN);
        Sanctum::actingAs($admin);

        $adminResponse = $this->getJson('/api/chat/sessions?search=Johnson')
            ->assertOk();
        $adminData = $adminResponse->json('data');
        $this->assertCount(1, $adminData, 'Admin assigned to Support should not see Sales clients.');
        $this->assertEquals('Johnson Support', $adminData[0]['client_name']);

        // Admin cannot initiate chat with client from unassigned department
        $this->postJson('/api/chat/session-for-client', [
            'client_id' => $clientSales->id,
            'platform' => 'whatsapp',
        ])->assertForbidden();

        // 2. Agent assigned to Support only
        $agent = $this->createChatUser($bank, [$deptSupport], User::ROLE_AGENT);
        Sanctum::actingAs($agent);

        $agentResponse = $this->getJson('/api/chat/sessions?search=Johnson')
            ->assertOk();
        $agentData = $agentResponse->json('data');
        $this->assertCount(1, $agentData, 'Agent assigned to Support should not see Sales clients.');
        $this->assertEquals('Johnson Support', $agentData[0]['client_name']);

        // 3. SuperAdmin is exempt and sees all departments
        $superAdmin = $this->createSuperAdmin();
        Sanctum::actingAs($superAdmin);

        $superResponse = $this->getJson('/api/chat/sessions?search=Johnson')
            ->assertOk();
        $superData = $superResponse->json('data');
        $this->assertCount(2, $superData, 'SuperAdmin should see clients from all departments.');
    }

    public function test_session_for_client_creates_session_and_enforces_bank_and_department_scope(): void
    {
        $bankA = Bank::query()->create(['name' => 'Bank Alpha', 'code' => 'ALPHA', 'status' => 'Active']);
        $bankB = Bank::query()->create(['name' => 'Bank Beta', 'code' => 'BETA', 'status' => 'Active']);
        $deptA = Department::query()->create(['name' => 'Support', 'code' => 'SUPP']);
        $deptB = Department::query()->create(['name' => 'Billing', 'code' => 'BILL']);

        $agent = $this->createChatUser($bankA, [$deptA], User::ROLE_AGENT);

        $validClient = Client::query()->create([
            'bank_id' => $bankA->id,
            'name' => 'Valid Client',
            'phone' => '+27827777777',
            'bank_name' => $bankA->name,
        ]);
        $validClient->departments()->sync([$deptA->id]);

        $otherBankClient = Client::query()->create([
            'bank_id' => $bankB->id,
            'name' => 'Other Bank Client',
            'phone' => '+27828888888',
            'bank_name' => $bankB->name,
        ]);
        $otherBankClient->departments()->sync([$deptA->id]);

        $otherDeptClient = Client::query()->create([
            'bank_id' => $bankA->id,
            'name' => 'Other Dept Client',
            'phone' => '+27829999999',
            'bank_name' => $bankA->name,
        ]);
        $otherDeptClient->departments()->sync([$deptB->id]);

        Sanctum::actingAs($agent);

        // 1. Authorized client succeeds
        $this->postJson('/api/chat/session-for-client', [
            'client_id' => $validClient->id,
            'platform' => 'whatsapp',
        ])
            ->assertOk()
            ->assertJsonPath('client_id', $validClient->id)
            ->assertJsonPath('phone', '+27827777777')
            ->assertJsonPath('client_name', 'Valid Client');

        // 2. Client from other bank forbidden
        $this->postJson('/api/chat/session-for-client', [
            'client_id' => $otherBankClient->id,
            'platform' => 'whatsapp',
        ])->assertForbidden();

        // 3. Client from other department forbidden
        $this->postJson('/api/chat/session-for-client', [
            'client_id' => $otherDeptClient->id,
            'platform' => 'whatsapp',
        ])->assertForbidden();
    }

    public function test_search_does_not_duplicate_client_when_client_already_has_chat_session(): void
    {
        $bank = Bank::query()->create(['name' => 'Bank Alpha', 'code' => 'ALPHA', 'status' => 'Active']);
        $dept = Department::query()->create(['name' => 'Support', 'code' => 'SUPP']);

        $user = $this->createChatUser($bank, [$dept], User::ROLE_ADMIN);

        $client = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Unique SearchClient',
            'phone' => '+27823334444',
            'account_number' => 'ACC-998877',
            'bank_name' => $bank->name,
        ]);
        $client->departments()->sync([$dept->id]);

        $session = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => 'Unique SearchClient',
            'phone' => '+27823334444',
            'status' => 'active',
            'platform' => 'whatsapp',
            'waba_phone_number_id' => 'waba-1',
            'last_message' => 'Active conversation',
            'unread_count' => 0,
        ]);

        Sanctum::actingAs($user);

        // Search by name
        $response = $this->getJson('/api/chat/sessions?search=Unique')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data, 'Client should only appear once as their chat session, not duplicated.');
        $this->assertEquals($session->id, $data[0]['id']);

        // Search by account number
        $accResponse = $this->getJson('/api/chat/sessions?search=ACC-998877')
            ->assertOk();

        $accData = $accResponse->json('data');
        $this->assertCount(1, $accData, 'Client found by account number should appear once as their chat session.');
        $this->assertEquals($session->id, $accData[0]['id']);
    }

    public function test_unauthorized_filter_aborts_forbidden(): void
    {
        $bankA = Bank::query()->create(['name' => 'Bank Alpha', 'code' => 'ALPHA', 'status' => 'Active']);
        $bankB = Bank::query()->create(['name' => 'Bank Beta', 'code' => 'BETA', 'status' => 'Active']);
        $deptA = Department::query()->create(['name' => 'Support', 'code' => 'SUPP']);
        $deptB = Department::query()->create(['name' => 'Billing', 'code' => 'BILL']);

        $agent = $this->createChatUser($bankA, [$deptA], User::ROLE_AGENT);
        Sanctum::actingAs($agent);

        // Filter for bank the user does not belong to -> 403
        $this->getJson('/api/chat/sessions?search=Test&bank_id=' . $bankB->id)->assertForbidden();

        // Filter for department the user does not belong to -> 403
        $this->getJson('/api/chat/sessions?search=Test&department_id=' . $deptB->id)->assertForbidden();
    }

    public function test_mark_unread_restores_the_unread_filter_after_opening_a_chat(): void
    {
        $bank = Bank::query()->create(['name' => 'Bank Alpha', 'code' => 'ALPHA', 'status' => 'Active']);
        $department = Department::query()->create(['name' => 'Support', 'code' => 'SUPP']);
        $user = $this->createChatUser($bank, [$department], User::ROLE_AGENT);

        $session = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_name' => 'Accidentally Opened Chat',
            'phone' => '+27821112222',
            'status' => 'active',
            'platform' => 'web',
            'unread_count' => 2,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/chat/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->postJson("/api/chat/sessions/{$session->id}/mark-unread")
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->getJson('/api/chat/sessions?status=unread')
            ->assertOk()
            ->assertJsonPath('data.0.id', $session->id);

        $this->getJson("/api/chat/sessions/{$session->id}?peek=1")
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->postJson("/api/chat/sessions/{$session->id}/mark-unread")
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $session->update(['unread_count' => 3]);
        $this->postJson("/api/chat/sessions/{$session->id}/mark-unread")
            ->assertOk()
            ->assertJsonPath('unread_count', 3);
    }

    public function test_mark_unread_requires_chat_management_permission_and_session_access(): void
    {
        $bankA = Bank::query()->create(['name' => 'Bank Alpha', 'code' => 'ALPHA', 'status' => 'Active']);
        $bankB = Bank::query()->create(['name' => 'Bank Beta', 'code' => 'BETA', 'status' => 'Active']);
        $department = Department::query()->create(['name' => 'Support', 'code' => 'SUPP']);
        $user = $this->createChatUser($bankA, [$department], User::ROLE_AGENT);

        $ownSession = ChatSession::query()->create([
            'bank_id' => $bankA->id,
            'client_name' => 'Own Chat',
            'status' => 'active',
            'platform' => 'web',
            'unread_count' => 0,
        ]);
        $otherSession = ChatSession::query()->create([
            'bank_id' => $bankB->id,
            'client_name' => 'Other Bank Chat',
            'status' => 'active',
            'platform' => 'web',
            'unread_count' => 0,
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/chat/sessions/{$otherSession->id}/mark-unread")->assertForbidden();

        Role::query()->where('code', User::ROLE_AGENT)->firstOrFail()
            ->permissions()->sync($this->permissionIds(['view_live_chat']));
        $this->postJson("/api/chat/sessions/{$ownSession->id}/mark-unread")->assertForbidden();

        $this->assertDatabaseHas('chat_sessions', ['id' => $ownSession->id, 'unread_count' => 0]);
        $this->assertDatabaseHas('chat_sessions', ['id' => $otherSession->id, 'unread_count' => 0]);
    }

    private function createChatUser(Bank $bank, array $departments, string $roleCode): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();
        $role->permissions()->sync($this->permissionIds(['view_live_chat', 'send_whatsapp']));

        $user = User::query()->create([
            'name' => 'Chat User ' . Str::random(4),
            'email' => 'chat-user-' . Str::uuid() . '@example.test',
            'password' => Hash::make('Password123!'),
            'password_changed_at' => now(),
            'password_reset_required' => false,
            'role' => $roleCode,
            'status' => 'Active',
            'bank_id' => $bank->id,
        ]);

        $user->roles()->sync([$role->id]);
        $user->banks()->sync([$bank->id]);
        $user->departments()->sync(collect($departments)->pluck('id')->all());

        return $user->fresh(['bank', 'banks', 'departments', 'roles']);
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

    private function createSuperAdmin(): User
    {
        $role = Role::query()->where('code', User::ROLE_SUPER_ADMIN)->firstOrFail();
        $role->permissions()->sync($this->permissionIds(['view_live_chat', 'send_whatsapp']));

        $user = User::query()->create([
            'name' => 'Super Admin ' . Str::random(4),
            'email' => 'super-admin-' . Str::uuid() . '@example.test',
            'password' => Hash::make('Password123!'),
            'password_changed_at' => now(),
            'password_reset_required' => false,
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
        ]);

        $user->roles()->sync([$role->id]);

        return $user->fresh(['roles']);
    }
}
