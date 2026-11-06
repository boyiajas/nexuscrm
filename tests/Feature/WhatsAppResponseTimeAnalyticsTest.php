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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppResponseTimeAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $agentUser;
    protected User $regularUser;
    protected Bank $bank;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['code' => User::ROLE_SUPER_ADMIN], [
            'name' => 'Super Administrator',
            'is_system' => true,
            'is_active' => true,
        ]);

        $agentRole = Role::firstOrCreate(['code' => 'agent'], [
            'name' => 'Agent',
            'is_system' => false,
            'is_active' => true,
        ]);

        $department = Department::firstOrCreate(['name' => 'Customer Care']);
        $this->bank = Bank::firstOrCreate(['code' => 'SB001'], ['name' => 'Standard Bank', 'code' => 'SB001']);

        $this->superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'department_id' => $department->id,
            'status' => 'Active',
            'password_changed_at' => now(),
        ]);
        $this->superAdmin->roles()->sync([$adminRole->id]);

        $this->agentUser = User::factory()->create([
            'name' => 'Sarah Miller',
            'email' => 'sarah@example.com',
            'role' => 'agent',
            'department_id' => $department->id,
            'status' => 'Active',
            'password_changed_at' => now(),
        ]);
        $this->agentUser->roles()->sync([$agentRole->id]);

        $this->regularUser = User::factory()->create([
            'role' => 'client_viewer',
            'department_id' => $department->id,
            'status' => 'Active',
            'password_changed_at' => now(),
        ]);

        $this->client = Client::query()->create([
            'name' => 'John Doe',
            'phone' => '+27821234567',
            'bank_id' => $this->bank->id,
        ]);

        WhatsappAccount::query()->create([
            'name' => 'Main Care Line',
            'app_id' => '347591848299284',
            'app_secret' => 'dummy_secret',
            'access_token' => 'dummy_token',
            'waba_id' => '1455412218881488',
            'phone_number_id' => '1455412218881488',
            'display_phone_number' => '+27 61 064 7115',
            'webhook_verify_token' => 'dummy_verify_token',
            'bank_id' => $this->bank->id,
        ]);
    }

    public function test_super_admin_can_retrieve_response_times_analytics_with_all_levels(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $session = ChatSession::query()->create([
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'phone' => $this->client->phone,
            'bank_id' => $this->bank->id,
            'agent_id' => $this->agentUser->id,
            'status' => 'active',
            'platform' => 'whatsapp',
            'waba_phone_number_id' => '1455412218881488',
        ]);

        $baseTime = Carbon::now()->subHours(5);

        // Turn 1: Client sends at 00m, Agent replies at 03m 30s (210s)
        ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'sender' => 'client',
            'content' => 'Hello, I have an inquiry about my payment.',
            'sent_at' => $baseTime->copy(),
        ]);

        ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'user_id' => $this->agentUser->id,
            'sender' => 'agent',
            'content' => 'Hi John, thank you for contacting us. How can I help?',
            'sent_at' => $baseTime->copy()->addSeconds(210),
        ]);

        // Turn 2: Client sends at 10m, Agent replies at 25m (900s = 15m)
        ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'sender' => 'client',
            'content' => 'Can you send my statement?',
            'sent_at' => $baseTime->copy()->addMinutes(10),
        ]);

        ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'user_id' => $this->agentUser->id,
            'sender' => 'agent',
            'content' => 'Your statement has been generated and sent.',
            'sent_at' => $baseTime->copy()->addMinutes(25),
        ]);

        // Turn 3: Client sends at 30m, Agent has not replied yet (Pending)
        ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'sender' => 'client',
            'content' => 'Thanks, what is the interest rate?',
            'sent_at' => $baseTime->copy()->addMinutes(30),
        ]);

        $response = $this->getJson('/api/analytics/response-times?sla_threshold_minutes=15');
        $response->assertOk();

        // 1. Overall Summary Assertions
        $this->assertEquals(3, $response->json('summary.total_inbound'));
        $this->assertEquals(2, $response->json('summary.answered_count'));
        $this->assertEquals(1, $response->json('summary.pending_count'));
        $this->assertEquals(555, $response->json('summary.avg_response_seconds')); // (210 + 900) / 2
        $this->assertStringContainsString('9m 15s', $response->json('summary.avg_response_human'));
        $this->assertEquals(100.0, $response->json('summary.sla_compliance_rate')); // both <= 15m

        // 2. Numbers Breakdown
        $numbers = $response->json('numbers');
        $this->assertNotEmpty($numbers);
        $wabaNum = collect($numbers)->firstWhere('phone_number_id', '1455412218881488');
        $this->assertNotNull($wabaNum);
        $this->assertEquals(3, $wabaNum['total_inbound']);
        $this->assertEquals(2, $wabaNum['answered_count']);
        $this->assertEquals(1, $wabaNum['pending_count']);
        $this->assertEquals('+27 61 064 7115', $wabaNum['display_phone_number']);

        // 3. Agents Breakdown
        $agents = $response->json('agents');
        $agentData = collect($agents)->firstWhere('user_id', $this->agentUser->id);
        $this->assertNotNull($agentData);
        $this->assertEquals('Sarah Miller', $agentData['name']);
        $this->assertEquals(2, $agentData['answered_count']);
        $this->assertEquals('9m 15s', $agentData['avg_response_human']);

        // 4. Clients Breakdown
        $clients = $response->json('clients');
        $clientData = collect($clients)->firstWhere('client_id', $this->client->id);
        $this->assertNotNull($clientData);
        $this->assertEquals('John Doe', $clientData['client_name']);
        $this->assertTrue($clientData['has_pending']);
        $this->assertEquals(3, $clientData['total_inbound']);
    }

    public function test_can_fetch_session_turn_audit_details(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $session = ChatSession::query()->create([
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'phone' => $this->client->phone,
            'bank_id' => $this->bank->id,
            'agent_id' => $this->agentUser->id,
            'status' => 'active',
            'platform' => 'whatsapp',
            'waba_phone_number_id' => '1455412218881488',
        ]);

        $baseTime = Carbon::now()->subMinutes(30);

        ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'sender' => 'client',
            'content' => 'Need help logging in',
            'sent_at' => $baseTime->copy(),
        ]);

        ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'user_id' => $this->agentUser->id,
            'sender' => 'agent',
            'content' => 'Reset link sent to your email.',
            'sent_at' => $baseTime->copy()->addMinutes(4),
        ]);

        $response = $this->getJson("/api/analytics/response-times/session-turns/{$session->id}");
        $response->assertOk();

        $turns = $response->json('turns');
        $this->assertCount(1, $turns);
        $this->assertEquals(1, $turns[0]['turn_index']);
        $this->assertEquals('answered', $turns[0]['status']);
        $this->assertEquals(240, $turns[0]['duration_seconds']);
        $this->assertEquals('4m', $turns[0]['duration_human']);
        $this->assertEquals('Sarah Miller', $turns[0]['user_name']);
        $this->assertEquals('Need help logging in', $turns[0]['inbound_content']);
        $this->assertEquals('Reset link sent to your email.', $turns[0]['reply_content']);
    }

    public function test_can_export_response_times_in_excel_and_csv(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $session = ChatSession::query()->create([
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'phone' => $this->client->phone,
            'bank_id' => $this->bank->id,
            'agent_id' => $this->agentUser->id,
            'status' => 'active',
            'platform' => 'whatsapp',
            'waba_phone_number_id' => '1455412218881488',
        ]);

        ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'sender' => 'client',
            'content' => 'Payment update needed',
            'sent_at' => now()->subMinutes(10),
        ]);

        ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'user_id' => $this->agentUser->id,
            'sender' => 'agent',
            'content' => 'Payment has cleared successfully.',
            'sent_at' => now()->subMinutes(8),
        ]);

        // Test Excel export for numbers
        $excelResponse = $this->get('/api/analytics/response-times/export?level=numbers&format=excel');
        $excelResponse->assertOk();
        $this->assertStringContainsString('application/vnd.ms-excel', $excelResponse->headers->get('content-type'));
        $excelContent = $excelResponse->streamedContent();
        $this->assertStringContainsString('WhatsApp Phone Numbers Response Time Report', $excelContent);
        $this->assertStringContainsString('1455412218881488', $excelContent);

        // Test CSV export for agents
        $csvResponse = $this->get('/api/analytics/response-times/export?level=agents&format=csv');
        $csvResponse->assertOk();
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('content-type'));
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('Agent WhatsApp Response Time Report', $csvContent);
        $this->assertStringContainsString('Sarah Miller', $csvContent);

        // Test CSV export for clients
        $clientCsvResponse = $this->get('/api/analytics/response-times/export?level=clients&format=csv');
        $clientCsvResponse->assertOk();
        $clientCsvContent = $clientCsvResponse->streamedContent();
        $this->assertStringContainsString('Client Turnaround & Response Time Report', $clientCsvContent);
        $this->assertStringContainsString('John Doe', $clientCsvContent);
    }

    public function test_unauthorized_user_is_forbidden_from_viewing_response_times(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/analytics/response-times');
        $response->assertForbidden();

        $exportResponse = $this->get('/api/analytics/response-times/export');
        $exportResponse->assertForbidden();
    }
}
