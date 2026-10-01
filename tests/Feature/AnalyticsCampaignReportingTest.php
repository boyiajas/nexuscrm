<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Campaign;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsCampaignReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_returns_all_campaigns_without_hardcoded_fifteen_limit(): void
    {
        [$user, $bank] = $this->createSuperAdminAndBank();
        Sanctum::actingAs($user);

        // Create 20 campaigns
        for ($i = 1; $i <= 20; $i++) {
            Campaign::query()->create([
                'name' => "Campaign {$i}",
                'bank_id' => $bank->id,
                'status' => 'Active',
                'channels' => ['whatsapp'],
                'created_at' => Carbon::now()->subDays($i),
            ]);
        }

        $response = $this->getJson('/api/analytics?timeframe=daily&date_range=year_to_date')->assertOk();
        $campaigns = $response->json('tables.campaigns');

        $this->assertCount(20, $campaigns);
        $this->assertSame('Campaign 1', $campaigns[0]['name']);
        $this->assertSame('Campaign 20', $campaigns[19]['name']);
        $this->assertNotNull($campaigns[0]['created_at']);
    }

    public function test_analytics_pulls_and_backdates_with_year_to_date_and_all_time(): void
    {
        [$user, $bank] = $this->createSuperAdminAndBank();
        Sanctum::actingAs($user);

        $client = Client::query()->create([
            'name' => 'Historical Client',
            'phone' => '+27820000099',
            'bank_id' => $bank->id,
        ]);

        $campaign = Campaign::query()->create([
            'name' => 'August Campaign',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
            'created_at' => Carbon::now()->subDays(45),
        ]);

        $message = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'body' => 'Historical text',
            'status' => 'sent',
            'created_at' => Carbon::now()->subDays(45),
        ]);

        $recipient = CampaignWhatsappRecipient::query()->create([
            'campaign_whatsapp_message_id' => $message->id,
            'whatsapp_message_id' => $message->id,
            'client_id' => $client->id,
            'status' => 'delivered',
        ]);
        \Illuminate\Support\Facades\DB::table('campaign_whatsapp_recipients')
            ->where('id', $recipient->id)
            ->update(['created_at' => Carbon::now()->subDays(45)]);

        // Last 30 days should NOT include the 45-day-old recipient
        $last30 = $this->getJson('/api/analytics?timeframe=daily&date_range=last_30_days')->assertOk();
        $this->assertSame('0', $last30->json('summary.dispatched'));

        // Year to date SHOULD include the 45-day-old recipient
        $ytd = $this->getJson('/api/analytics?timeframe=daily&date_range=year_to_date')->assertOk();
        $this->assertSame('1', $ytd->json('summary.dispatched'));
        $this->assertSame('1', $ytd->json('summary.delivered'));

        // All time SHOULD include the recipient
        $allTime = $this->getJson('/api/analytics?timeframe=daily&date_range=all_time')->assertOk();
        $this->assertSame('1', $allTime->json('summary.dispatched'));
        $this->assertSame('1', $allTime->json('summary.delivered'));
    }

    public function test_analytics_extended_date_ranges_filter_campaigns_and_summary(): void
    {
        [$user, $bank] = $this->createSuperAdminAndBank();
        Sanctum::actingAs($user);

        $c1 = Campaign::query()->create([
            'name' => 'Recent Campaign (15d)',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);
        \Illuminate\Support\Facades\DB::table('campaigns')->where('id', $c1->id)->update(['created_at' => Carbon::now()->subDays(15)]);

        $c2 = Campaign::query()->create([
            'name' => 'Quarterly Campaign (60d)',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);
        \Illuminate\Support\Facades\DB::table('campaigns')->where('id', $c2->id)->update(['created_at' => Carbon::now()->subDays(60)]);

        $c3 = Campaign::query()->create([
            'name' => 'Mid-Year Campaign (150d)',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);
        \Illuminate\Support\Facades\DB::table('campaigns')->where('id', $c3->id)->update(['created_at' => Carbon::now()->subDays(150)]);

        $c4 = Campaign::query()->create([
            'name' => 'Annual Campaign (300d)',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);
        \Illuminate\Support\Facades\DB::table('campaigns')->where('id', $c4->id)->update(['created_at' => Carbon::now()->subDays(300)]);

        $c5 = Campaign::query()->create([
            'name' => 'Biennial Campaign (500d)',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);
        \Illuminate\Support\Facades\DB::table('campaigns')->where('id', $c5->id)->update(['created_at' => Carbon::now()->subDays(500)]);

        // 1. Last 30 days
        $res30 = $this->getJson('/api/analytics?date_range=last_30_days')->assertOk();
        $this->assertCount(1, $res30->json('tables.campaigns'));
        $this->assertSame('Recent Campaign (15d)', $res30->json('tables.campaigns.0.name'));

        // 2. 3 months
        $res3m = $this->getJson('/api/analytics?date_range=3_months')->assertOk();
        $this->assertCount(2, $res3m->json('tables.campaigns'));

        // 3. 6 months
        $res6m = $this->getJson('/api/analytics?date_range=6_months')->assertOk();
        $this->assertCount(3, $res6m->json('tables.campaigns'));

        // 4. 1 year
        $res1y = $this->getJson('/api/analytics?date_range=1_year')->assertOk();
        $this->assertCount(4, $res1y->json('tables.campaigns'));

        // 5. 2 years
        $res2y = $this->getJson('/api/analytics?date_range=2_years')->assertOk();
        $this->assertCount(5, $res2y->json('tables.campaigns'));

        // 6. All time
        $resAll = $this->getJson('/api/analytics?date_range=all_time')->assertOk();
        $this->assertCount(5, $resAll->json('tables.campaigns'));
    }

    public function test_analytics_filters_by_bank_id(): void
    {
        [$user, $bankA] = $this->createSuperAdminAndBank();
        Sanctum::actingAs($user);

        $bankB = Bank::query()->create([
            'name' => 'Bank B',
            'code' => 'bank-b',
            'status' => 'Active',
        ]);

        Campaign::query()->create([
            'name' => 'Campaign For Bank A',
            'bank_id' => $bankA->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        Campaign::query()->create([
            'name' => 'Campaign For Bank B',
            'bank_id' => $bankB->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        // All institutions
        $resAll = $this->getJson('/api/analytics?bank_id=all')->assertOk();
        $this->assertCount(2, $resAll->json('tables.campaigns'));

        // Filter Bank A
        $resA = $this->getJson("/api/analytics?bank_id={$bankA->id}")->assertOk();
        $campaignsA = $resA->json('tables.campaigns');
        $this->assertCount(1, $campaignsA);
        $this->assertSame('Campaign For Bank A', $campaignsA[0]['name']);

        // Filter Bank B
        $resB = $this->getJson("/api/analytics?bank_id={$bankB->id}")->assertOk();
        $campaignsB = $resB->json('tables.campaigns');
        $this->assertCount(1, $campaignsB);
        $this->assertSame('Campaign For Bank B', $campaignsB[0]['name']);
    }

    public function test_analytics_reconciles_delivered_read_unread_failed_and_marketing_cost(): void
    {
        [$user, $bank] = $this->createSuperAdminAndBank();
        Sanctum::actingAs($user);

        $campaign = Campaign::query()->create([
            'name' => 'FINCHOICE WHATSAPP SETTLEMENTS 19-09-26',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        $message = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'template_name' => 'fin_choice_special_discount_offer_11_09_2026',
            'total' => 5,
            'delivered' => 3,
            'pending' => 1,
            'failed' => 1,
            'sent_at' => now(),
        ]);

        $clients = [];
        for ($i = 1; $i <= 5; $i++) {
            $clients[] = Client::query()->create([
                'name' => "Client {$i}",
                'phone' => "+278200000{$i}0",
                'bank_id' => $bank->id,
            ]);
        }

        // 3 delivered read
        for ($i = 0; $i < 3; $i++) {
            CampaignWhatsappRecipient::query()->create([
                'whatsapp_message_id' => $message->id,
                'client_id' => $clients[$i]->id,
                'status' => 'Delivered',
            ]);
        }

        // 1 delivered unread (sent)
        CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $message->id,
            'client_id' => $clients[3]->id,
            'status' => 'Sent',
        ]);

        // 1 failed
        CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $message->id,
            'client_id' => $clients[4]->id,
            'status' => 'Failed',
            'error_code' => '131026',
            'error_message' => 'Message undeliverable',
        ]);

        $response = $this->getJson('/api/analytics?timeframe=daily&date_range=all_time')->assertOk();
        $campaigns = $response->json('tables.campaigns');

        $target = collect($campaigns)->firstWhere('name', 'FINCHOICE WHATSAPP SETTLEMENTS 19-09-26');
        $this->assertNotNull($target);

        $this->assertSame('5', $target['sent']);
        $this->assertSame('3', $target['delivered_read']);
        $this->assertSame('1', $target['delivered_unread']);
        $this->assertSame('1', $target['failed']);
        $this->assertSame('4', $target['delivered']);
        // 4 delivered out of 5 sent = 80.0%
        $this->assertSame('80.0%', $target['delivery']);
        // Marketing category & rate
        $this->assertSame('Marketing', $target['template_category']);
        $this->assertSame('$0.0175', $target['rate']);
        // 4 accepted * 0.0175 = $0.07
        $this->assertSame('$0.07', $target['cost']);
    }

    public function test_analytics_reconciles_template_performance_agent_stats_and_overall_kpis(): void
    {
        [$superAdmin, $bank] = $this->createSuperAdminAndBank();

        $staffUser = User::factory()->create([
            'name' => 'Alice Staff',
            'email' => 'alice@example.com',
            'bank_id' => $bank->id,
            'role' => 'STAFF',
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);

        $adminUser = User::factory()->create([
            'name' => 'Bob Admin',
            'email' => 'bob@example.com',
            'bank_id' => $bank->id,
            'role' => User::ROLE_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);

        \App\Models\WhatsappTemplateCache::query()->create([
            'friendly_name' => 'september_discount_offer',
            'sid' => 'september_discount_offer',
            'meta_id' => '1234567890',
            'category' => 'MARKETING',
            'status' => 'APPROVED',
        ]);

        \App\Models\WhatsappTemplateCache::query()->create([
            'friendly_name' => 'monthly_payment_reminder',
            'sid' => 'monthly_payment_reminder',
            'meta_id' => '9876543210',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
        ]);

        // Campaign 1: Marketing, created by Super Admin
        $c1 = Campaign::query()->create([
            'name' => 'September Settlement Promo',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);
        $msg1 = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $c1->id,
            'created_by_user_id' => $superAdmin->id,
            'template_name' => 'september_discount_offer',
            'template_sid' => 'september_discount_offer',
            'total' => 4,
            'delivered' => 3,
            'failed' => 1,
            'sent_at' => now(),
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $client = Client::query()->create([
                'name' => "Marketing Client {$i}",
                'phone' => "+2782111000{$i}",
                'bank_id' => $bank->id,
            ]);
            CampaignWhatsappRecipient::query()->create([
                'whatsapp_message_id' => $msg1->id,
                'client_id' => $client->id,
                'status' => $i <= 2 ? 'Delivered' : ($i === 3 ? 'Sent' : 'Failed'),
                'last_response' => $i === 1 ? 'Interested' : null,
            ]);
        }

        // Campaign 2: Utility, created by Staff User
        $c2 = Campaign::query()->create([
            'name' => 'Monthly Reminder Notice',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);
        $msg2 = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $c2->id,
            'created_by_user_id' => $staffUser->id,
            'template_name' => 'monthly_payment_reminder',
            'template_sid' => 'monthly_payment_reminder',
            'total' => 3,
            'delivered' => 2,
            'failed' => 1,
            'sent_at' => now(),
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $client = Client::query()->create([
                'name' => "Utility Client {$i}",
                'phone' => "+2782222000{$i}",
                'bank_id' => $bank->id,
            ]);
            CampaignWhatsappRecipient::query()->create([
                'whatsapp_message_id' => $msg2->id,
                'client_id' => $client->id,
                'status' => $i <= 2 ? 'Delivered' : 'Failed',
            ]);
        }

        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/analytics?timeframe=daily&date_range=all_time')->assertOk();

        // 1. WhatsApp Template Breakdown
        $templates = collect($response->json('tables.templates'));
        $tplMarketing = $templates->firstWhere('name', 'september_discount_offer');
        $this->assertNotNull($tplMarketing);
        $this->assertSame('Marketing', $tplMarketing['category']);
        $this->assertSame('September Settlement Promo', $tplMarketing['campaign']);
        $this->assertSame('4', $tplMarketing['sent']);
        $this->assertSame('75.0%', $tplMarketing['delivery']);
        $this->assertSame('25.0%', $tplMarketing['reply']);
        $this->assertSame('$0.0175', $tplMarketing['rate']);
        // 3 accepted * 0.0175 = $0.05
        $this->assertSame('$0.05', $tplMarketing['cost']);

        $tplUtility = $templates->firstWhere('name', 'monthly_payment_reminder');
        $this->assertNotNull($tplUtility);
        $this->assertSame('Utility', $tplUtility['category']);
        $this->assertSame('Monthly Reminder Notice', $tplUtility['campaign']);
        $this->assertSame('3', $tplUtility['sent']);
        $this->assertSame('66.7%', $tplUtility['delivery']);
        $this->assertSame('$0.0076', $tplUtility['rate']);
        // 2 accepted * 0.0076 = $0.02
        $this->assertSame('$0.02', $tplUtility['cost']);

        // Verify no mock placeholders
        foreach ($templates as $t) {
            $this->assertStringNotContainsString('TBD', $t['campaign']);
            $this->assertStringNotContainsString('TBD', $t['sub_campaign']);
        }

        // 2. Agent & User Statistics
        $agents = collect($response->json('tables.agents'));
        $this->assertTrue($agents->contains('name', 'Alice Staff'));
        $this->assertTrue($agents->contains('name', 'Bob Admin'));

        $agSuper = $agents->firstWhere('name', $superAdmin->name);
        $this->assertSame(1, $agSuper['campaigns']);
        $this->assertSame('4', $agSuper['dispatched']);
        $this->assertSame('25.0%', $agSuper['replyRate']);

        $agStaff = $agents->firstWhere('name', 'Alice Staff');
        $this->assertSame(1, $agStaff['campaigns']);
        $this->assertSame('3', $agStaff['dispatched']);

        $agAdmin = $agents->firstWhere('name', 'Bob Admin');
        $this->assertSame(0, $agAdmin['campaigns']);
        $this->assertSame('0', $agAdmin['dispatched']);
        $this->assertSame('—', $agAdmin['responseTime']);

        // 3. Overall Statistics & Spend
        $this->assertSame('7', $response->json('summary.dispatched'));
        $this->assertSame('5', $response->json('summary.delivered'));
        $this->assertSame('71.4%', $response->json('summary.delivery_rate'));

        $this->assertSame('$0.07', $response->json('spend.total'));
        $this->assertSame('$0.05', $response->json('spend.marketing.cost'));
        $this->assertSame('$0.02', $response->json('spend.utility.cost'));
    }

    private function createSuperAdminAndBank(): array
    {
        $bank = Bank::query()->create([
            'name' => 'Report Bank',
            'code' => 'report-bank',
            'status' => 'Active',
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

        return [$user->fresh('roles'), $bank];
    }
}
