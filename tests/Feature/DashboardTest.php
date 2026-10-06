<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\Campaign;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\ChatSession;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Bank $bankA;
    protected Bank $bankB;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['code' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Super Administrator', 'is_system' => true, 'is_active' => true]
        );

        $this->admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);
        $this->admin->roles()->sync([$role->id]);

        $this->bankA = Bank::create(['name' => 'Bank Alpha', 'code' => 'ALPHA', 'status' => 'active']);
        $this->bankB = Bank::create(['name' => 'Bank Beta', 'code' => 'BETA', 'status' => 'active']);
    }

    public function test_dashboard_defaults_to_today_and_loads_accurate_metrics(): void
    {
        Sanctum::actingAs($this->admin);

        // Client created today
        $clientToday = Client::create([
            'name' => 'John Doe',
            'phone' => '+27820000001',
            'bank_id' => $this->bankA->id,
            'status' => 'Active',
            'created_at' => Carbon::now(),
        ]);

        // Client created 10 days ago
        $clientPast = Client::create([
            'name' => 'Jane Past',
            'phone' => '+27820000002',
            'bank_id' => $this->bankA->id,
            'status' => 'Active',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        // Campaign today
        $campaignToday = Campaign::create([
            'name' => 'Today Campaign',
            'bank_id' => $this->bankA->id,
            'status' => 'Active',
            'channels' => json_encode(['whatsapp']),
            'created_at' => Carbon::now(),
        ]);

        $messageToday = CampaignWhatsappMessage::create([
            'campaign_id' => $campaignToday->id,
            'template_name' => 'reminder_template',
            'language' => 'en',
            'created_at' => Carbon::now(),
        ]);

        CampaignWhatsappRecipient::create([
            'whatsapp_message_id' => $messageToday->id,
            'client_id' => $clientToday->id,
            'phone_number' => $clientToday->phone,
            'status' => 'delivered',
            'created_at' => Carbon::now(),
        ]);

        // Past campaign and recipient (10 days ago)
        $campaignPast = Campaign::create([
            'name' => 'Past Campaign',
            'bank_id' => $this->bankA->id,
            'status' => 'Completed',
            'channels' => json_encode(['whatsapp']),
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $messagePast = CampaignWhatsappMessage::create([
            'campaign_id' => $campaignPast->id,
            'template_name' => 'old_template',
            'language' => 'en',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $pastRecipient = CampaignWhatsappRecipient::create([
            'whatsapp_message_id' => $messagePast->id,
            'client_id' => $clientPast->id,
            'phone_number' => $clientPast->phone,
            'status' => 'failed',
        ]);
        \Illuminate\Support\Facades\DB::table('campaign_whatsapp_recipients')
            ->where('id', $pastRecipient->id)
            ->update(['created_at' => Carbon::now()->subDays(10)]);

        \Illuminate\Support\Facades\DB::table('clients')
            ->where('id', $clientPast->id)
            ->update(['created_at' => Carbon::now()->subDays(10)]);

        $response = $this->getJson('/api/dashboard');
        $response->assertOk();

        $response->assertJsonPath('filter.date_range', 'today');
        // Only today's message is counted in summary
        $response->assertJsonPath('summary.total_messages', 1);
        $response->assertJsonPath('summary.total_delivered', 1);
        $response->assertJsonPath('summary.total_failed', 0);
        $response->assertJsonPath('summary.delivery_rate', 100);
    }

    public function test_dashboard_open_chats_and_requiring_attention_are_dynamic_and_accurate(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'name' => 'Chat Client',
            'phone' => '+27821111111',
            'bank_id' => $this->bankA->id,
            'status' => 'Active',
        ]);

        // Case 1: Active chat with NO unread messages
        $session1 = ChatSession::create([
            'client_id' => $client->id,
            'bank_id' => $this->bankA->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'status' => 'active',
            'unread_count' => 0,
        ]);

        $res1 = $this->getJson('/api/dashboard');
        $res1->assertOk();
        $res1->assertJsonPath('summary.open_chats', 1);
        $res1->assertJsonPath('summary.chats_requiring_attention', 0);

        // Case 2: Second active chat with unread messages
        $session2 = ChatSession::create([
            'client_id' => $client->id,
            'bank_id' => $this->bankA->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'status' => 'active',
            'unread_count' => 4,
        ]);

        $res2 = $this->getJson('/api/dashboard');
        $res2->assertOk();
        $res2->assertJsonPath('summary.open_chats', 2);
        $res2->assertJsonPath('summary.chats_requiring_attention', 1);

        // Case 3: Closed chat should not be counted as open or requiring attention
        $session3 = ChatSession::create([
            'client_id' => $client->id,
            'bank_id' => $this->bankA->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'status' => 'closed',
            'unread_count' => 5,
        ]);

        $res3 = $this->getJson('/api/dashboard');
        $res3->assertOk();
        $res3->assertJsonPath('summary.open_chats', 2);
        $res3->assertJsonPath('summary.chats_requiring_attention', 1);
    }

    public function test_dashboard_filters_by_date_ranges(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'name' => 'Historical Client',
            'phone' => '+27822222222',
            'bank_id' => $this->bankA->id,
            'status' => 'Active',
        ]);

        $campaign = Campaign::create([
            'name' => 'Old Campaign',
            'bank_id' => $this->bankA->id,
            'status' => 'Active',
            'channels' => json_encode(['whatsapp']),
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $message = CampaignWhatsappMessage::create([
            'campaign_id' => $campaign->id,
            'template_name' => 'old_msg',
            'language' => 'en',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $recipient = CampaignWhatsappRecipient::create([
            'whatsapp_message_id' => $message->id,
            'client_id' => $client->id,
            'phone_number' => $client->phone,
            'status' => 'delivered',
        ]);
        \Illuminate\Support\Facades\DB::table('campaign_whatsapp_recipients')
            ->where('id', $recipient->id)
            ->update(['created_at' => Carbon::now()->subDays(10)]);

        // 1. Today filter should NOT include 10 days ago
        $resToday = $this->getJson('/api/dashboard?date_range=today');
        $resToday->assertOk();
        $resToday->assertJsonPath('summary.total_messages', 0);

        // 2. 1 Week filter should NOT include 10 days ago
        $res1Week = $this->getJson('/api/dashboard?date_range=1_week');
        $res1Week->assertOk();
        $res1Week->assertJsonPath('summary.total_messages', 0);

        // 3. 2 Weeks filter SHOULD include 10 days ago
        $res2Weeks = $this->getJson('/api/dashboard?date_range=2_weeks');
        $res2Weeks->assertOk();
        $res2Weeks->assertJsonPath('summary.total_messages', 1);
        $res2Weeks->assertJsonPath('summary.total_delivered', 1);

        // 4. 1 Month filter SHOULD include 10 days ago
        $res1Month = $this->getJson('/api/dashboard?date_range=1_month');
        $res1Month->assertOk();
        $res1Month->assertJsonPath('summary.total_messages', 1);

        // 5. 1 Year filter SHOULD include 10 days ago
        $res1Year = $this->getJson('/api/dashboard?date_range=1_year');
        $res1Year->assertOk();
        $res1Year->assertJsonPath('summary.total_messages', 1);
    }

    public function test_dashboard_filters_by_multiple_banks(): void
    {
        Sanctum::actingAs($this->admin);

        $clientA = Client::create([
            'name' => 'Client Bank Alpha',
            'phone' => '+27823333331',
            'bank_id' => $this->bankA->id,
            'status' => 'Active',
        ]);

        $clientB = Client::create([
            'name' => 'Client Bank Beta',
            'phone' => '+27823333332',
            'bank_id' => $this->bankB->id,
            'status' => 'Active',
        ]);

        // Chat in Bank A
        ChatSession::create([
            'client_id' => $clientA->id,
            'bank_id' => $this->bankA->id,
            'client_name' => $clientA->name,
            'phone' => $clientA->phone,
            'status' => 'active',
            'unread_count' => 1,
        ]);

        // Chat in Bank B
        ChatSession::create([
            'client_id' => $clientB->id,
            'bank_id' => $this->bankB->id,
            'client_name' => $clientB->name,
            'phone' => $clientB->phone,
            'status' => 'active',
            'unread_count' => 1,
        ]);

        // Filter Bank A only
        $resA = $this->getJson("/api/dashboard?bank_ids={$this->bankA->id}");
        $resA->assertOk();
        $resA->assertJsonPath('summary.total_clients', 1);
        $resA->assertJsonPath('summary.open_chats', 1);

        // Filter Bank B only
        $resB = $this->getJson("/api/dashboard?bank_ids={$this->bankB->id}");
        $resB->assertOk();
        $resB->assertJsonPath('summary.total_clients', 1);
        $resB->assertJsonPath('summary.open_chats', 1);

        // Multi-select both Bank A and Bank B
        $resBoth = $this->getJson("/api/dashboard?bank_ids={$this->bankA->id},{$this->bankB->id}");
        $resBoth->assertOk();
        $resBoth->assertJsonPath('summary.total_clients', 2);
        $resBoth->assertJsonPath('summary.open_chats', 2);
    }

    public function test_dashboard_chats_attention_zero_when_all_chats_read(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'name' => 'All Read Client',
            'phone' => '+27824444444',
            'bank_id' => $this->bankA->id,
            'status' => 'Active',
        ]);

        ChatSession::create([
            'client_id' => $client->id,
            'bank_id' => $this->bankA->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'status' => 'active',
            'unread_count' => 0,
        ]);

        $res = $this->getJson('/api/dashboard');
        $res->assertOk();
        $res->assertJsonPath('summary.open_chats', 1);
        $res->assertJsonPath('summary.chats_requiring_attention', 0);
    }

    public function test_dashboard_scoped_agent_only_sees_accessible_banks(): void
    {
        $department = \App\Models\Department::firstOrCreate(['code' => 'coll'], ['name' => 'Collections']);
        $this->bankA->departments()->syncWithoutDetaching([$department->id]);
        $this->bankB->departments()->syncWithoutDetaching([$department->id]);

        $agentRole = Role::firstOrCreate(
            ['code' => User::ROLE_AGENT],
            ['name' => 'Agent', 'is_system' => true, 'is_active' => true]
        );

        $agent = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'status' => 'Active',
            'department_id' => $department->id,
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);
        $agent->roles()->sync([$agentRole->id]);

        // Assign only Bank A and department to agent
        $agent->banks()->sync([$this->bankA->id]);
        $agent->departments()->sync([$department->id]);

        $clientA = Client::create([
            'name' => 'Client A',
            'phone' => '+27825555551',
            'bank_id' => $this->bankA->id,
            'assigned_to_id' => $agent->id,
            'status' => 'Active',
        ]);
        $clientA->departments()->sync([$department->id]);

        $clientB = Client::create([
            'name' => 'Client B',
            'phone' => '+27825555552',
            'bank_id' => $this->bankB->id,
            'status' => 'Active',
        ]);
        $clientB->departments()->sync([$department->id]);

        Sanctum::actingAs($agent);

        $res = $this->getJson('/api/dashboard');
        $res->assertOk();
        // Agent should only see 1 client from Bank A, not Bank B
        $res->assertJsonPath('summary.total_clients', 1);
    }
}
