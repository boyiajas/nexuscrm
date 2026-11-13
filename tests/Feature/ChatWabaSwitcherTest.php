<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Client;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappNumberControl;
use App\Services\BankWabaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatWabaSwitcherTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Bank $bank1;
    protected Bank $bank2;
    protected WhatsappAccount $account1;
    protected WhatsappAccount $account2;

    protected function setUp(): void
    {
        parent::setUp();

        $superAdminRole = Role::firstOrCreate(
            ['code' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Super Administrator', 'is_system' => true, 'is_active' => true]
        );

        $department = Department::firstOrCreate(['name' => 'Recoveries']);

        $this->bank1 = Bank::firstOrCreate(
            ['code' => 'ABSA_UDB_1'],
            [
                'name' => 'ABSA UDB',
                'primary_whatsapp_number' => '+27 60 787 3113',
            ]
        );

        $this->bank2 = Bank::firstOrCreate(
            ['code' => 'ABSA_UDB_2'],
            [
                'name' => 'ABSA UDB 2',
                'primary_whatsapp_number' => '+27 67 613 4110',
            ]
        );

        $this->account1 = WhatsappAccount::create([
            'bank_id' => $this->bank1->id,
            'name' => 'ABSA UDB',
            'phone_number_id' => '111111111',
            'display_phone_number' => '+27 60 787 3113',
            'app_id' => 'app_1',
            'app_secret' => 'secret_1',
            'access_token' => 'token_1',
            'waba_id' => 'waba_1',
            'webhook_verify_token' => 'verify_1',
        ]);
        $this->bank1->update(['whatsapp_account_id' => $this->account1->id]);

        $this->account2 = WhatsappAccount::create([
            'bank_id' => $this->bank2->id,
            'name' => 'ABSA UDB 2',
            'phone_number_id' => '222222222',
            'display_phone_number' => '+27 67 613 4110',
            'app_id' => 'app_2',
            'app_secret' => 'secret_2',
            'access_token' => 'token_2',
            'waba_id' => 'waba_2',
            'webhook_verify_token' => 'verify_2',
        ]);
        $this->bank2->update(['whatsapp_account_id' => $this->account2->id]);

        $this->superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'bank_id' => $this->bank1->id,
            'department_id' => $department->id,
            'status' => 'Active',
            'password_changed_at' => now(),
        ]);
        $this->superAdmin->roles()->sync([$superAdminRole->id]);
    }

    public function test_super_admin_can_switch_waba_number_on_chat_session(): void
    {
        $client = Client::create([
            'name' => 'Sam Rebecca',
            'first_name' => 'Sam',
            'surname' => 'Rebecca',
            'phone' => '+27796408392',
            'bank_id' => $this->bank1->id,
            'status' => 'active',
        ]);

        $session = ChatSession::create([
            'client_id' => $client->id,
            'bank_id' => $this->bank1->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'platform' => 'whatsapp',
            'waba_phone_number_id' => '222222222', // initially stuck on second number
            'status' => 'active',
        ]);

        WhatsappNumberControl::create([
            'phone_number_id' => '111111111',
            'display_phone_number' => '+27 60 787 3113',
            'normalized_display_phone_number' => '27607873113',
            'quality_rating' => 'GREEN',
            'is_paused' => false,
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/chat/sessions/{$session->id}/waba-number", [
                'waba_number' => '111111111',
            ]);

        $response->assertOk()
            ->assertJsonPath('waba_phone_number_id', '111111111')
            ->assertJsonPath('waba_quality_rating', 'GREEN')
            ->assertJsonPath('waba_health_status', 'Good');

        $this->assertDatabaseHas('chat_sessions', [
            'id' => $session->id,
            'waba_phone_number_id' => '111111111',
        ]);
    }

    public function test_bank_waba_resolver_does_not_falsely_substring_match_numbered_variations(): void
    {
        $resolver = app(BankWabaResolver::class);

        // 'ABSA UDB 2' should resolve to bank2, NOT bank1 (even though bank1 name 'ABSA UDB' is a substring)
        $resolvedFor2 = $resolver->resolveBankForSender('222222222', '+27 67 613 4110', 'ABSA UDB 2');
        $this->assertNotNull($resolvedFor2);
        $this->assertSame($this->bank2->id, $resolvedFor2->id);

        // 'ABSA UDB' should resolve to bank1
        $resolvedFor1 = $resolver->resolveBankForSender('111111111', '+27 60 787 3113', 'ABSA UDB');
        $this->assertNotNull($resolvedFor1);
        $this->assertSame($this->bank1->id, $resolvedFor1->id);
    }

    public function test_updating_client_bank_syncs_chat_session(): void
    {
        $client = Client::create([
            'name' => 'Sam Rebecca',
            'first_name' => 'Sam',
            'surname' => 'Rebecca',
            'phone' => '+27796408392',
            'bank_id' => $this->bank2->id,
            'status' => 'active',
        ]);

        $dept = Department::firstOrCreate(['name' => 'Recoveries']);
        $client->departments()->sync([$dept->id]);

        $session = ChatSession::create([
            'client_id' => $client->id,
            'bank_id' => $this->bank2->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'platform' => 'whatsapp',
            'waba_phone_number_id' => '222222222',
            'status' => 'active',
        ]);

        // Now edit client to assign bank1
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/clients/{$client->id}", [
                'first_name' => 'Sam',
                'surname' => 'Rebecca',
                'phone' => '+27796408392',
                'bank_id' => $this->bank1->id,
                'department_ids' => [$dept->id],
            ]);

        $response->assertOk();

        $session->refresh();
        $this->assertSame($this->bank1->id, $session->bank_id);
        $this->assertSame('111111111', $session->waba_phone_number_id);
    }

    public function test_session_for_client_defaults_to_bank_primary_waba_when_omitted(): void
    {
        $client = Client::create([
            'name' => 'Sam Rebecca',
            'first_name' => 'Sam',
            'surname' => 'Rebecca',
            'phone' => '+27796408392',
            'bank_id' => $this->bank1->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/chat/session-for-client', [
                'client_id' => $client->id,
                'platform' => 'whatsapp',
                // waba_number omitted intentionally
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('chat_sessions', [
            'client_id' => $client->id,
            'waba_phone_number_id' => '111111111',
            'bank_id' => $this->bank1->id,
        ]);
    }

    public function test_sync_chat_waba_numbers_artisan_command_repairs_mismatched_sessions(): void
    {
        $client = Client::create([
            'name' => 'Sam Rebecca',
            'first_name' => 'Sam',
            'surname' => 'Rebecca',
            'phone' => '+27796408392',
            'bank_id' => $this->bank1->id,
            'status' => 'active',
        ]);

        $session = ChatSession::create([
            'client_id' => $client->id,
            'bank_id' => $this->bank1->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'platform' => 'whatsapp',
            'waba_phone_number_id' => '222222222', // mismatched from bank1 (should be 111111111)
            'status' => 'active',
        ]);

        $this->artisan('chat:sync-waba-numbers', ['--force' => true])
            ->expectsOutputToContain('1 session(s) successfully synchronized.')
            ->assertExitCode(0);

        $session->refresh();
        $this->assertSame('111111111', $session->waba_phone_number_id);
    }

    public function test_bank_restricted_agent_cannot_switch_to_unauthorized_waba(): void
    {
        $agentRole = Role::firstOrCreate(['code' => 'agent'], [
            'name' => 'Agent',
            'is_system' => false,
            'is_active' => true,
        ]);
        $agentRole->permissions()->firstOrCreate(['code' => 'view_live_chat'], ['name' => 'View Live Chat']);
        $agentRole->permissions()->firstOrCreate(['code' => 'send_whatsapp'], ['name' => 'Send WhatsApp']);

        $agent = User::factory()->create([
            'role' => 'agent',
            'bank_id' => $this->bank1->id,
            'status' => 'Active',
            'password_changed_at' => now(),
        ]);
        $agent->roles()->sync([$agentRole->id]);

        $client = Client::create([
            'name' => 'Sam Rebecca',
            'first_name' => 'Sam',
            'surname' => 'Rebecca',
            'phone' => '+27796408392',
            'bank_id' => $this->bank1->id,
            'status' => 'active',
        ]);

        $session = ChatSession::create([
            'client_id' => $client->id,
            'bank_id' => $this->bank1->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'platform' => 'whatsapp',
            'waba_phone_number_id' => '111111111',
            'status' => 'active',
        ]);

        // Attempt to switch to bank2's WABA number (not accessible to this agent)
        $response = $this->actingAs($agent, 'sanctum')
            ->patchJson("/api/chat/sessions/{$session->id}/waba-number", [
                'waba_number' => '222222222',
            ]);

        $response->assertStatus(403);
    }
}
