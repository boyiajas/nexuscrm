<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Campaign;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsappTemplateCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MetaBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::create([
            'app_name' => 'Nexus CRM',
            'meta_app_id' => '347591848299284',
            'meta_app_secret' => 'test_app_secret',
            'meta_access_token' => 'test_access_token',
            'meta_whatsapp_business_account_id' => '406811385845304',
            'meta_whatsapp_phone_number_id' => '1324173460771054',
            'meta_whatsapp_display_phone_number' => '+27110000000',
        ]);
    }

    protected function createSuperAdmin(): User
    {
        $role = Role::firstOrCreate(
            ['code' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Super Administrator']
        );

        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);

        $user->roles()->sync([$role->id]);

        return $user->fresh('roles');
    }

    public function test_admin_can_fetch_meta_billing_overview(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/406811385845304*' => Http::response([
                'id' => '406811385845304',
                'name' => 'Iconis CRM',
                'currency' => 'USD',
                'timezone_id' => '141',
                'status' => 'ACTIVE',
                'account_review_status' => 'APPROVED',
                'owner_business_info' => [
                    'id' => '1323375205187636',
                    'name' => 'ICON INFORMATION SYSTEMS',
                ],
            ], 200),
            'https://graph.facebook.com/v21.0/347591848299284*' => Http::response([
                'id' => '347591848299284',
                'name' => 'CRM System API',
                'category' => 'Business',
            ], 200),
            'https://graph.facebook.com/v21.0/1323375205187636*' => Http::response([
                'verification_status' => 'verified',
            ], 200),
            'https://graph.facebook.com/v21.0/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'scopes' => ['whatsapp_business_management', 'whatsapp_business_messaging'],
                ],
            ], 200),
        ]);

        $admin = $this->createSuperAdmin();

        $bank = Bank::create([
            'name' => 'Standard Bank',
            'code' => 'SBK',
        ]);

        $campaign = Campaign::create([
            'name' => 'Test Debt Campaign',
            'bank_id' => $bank->id,
            'status' => 'Completed',
            'channels' => ['whatsapp'],
        ]);

        $msg = CampaignWhatsappMessage::create([
            'campaign_id' => $campaign->id,
            'template_name' => 'settlement_offer',
            'template_sid' => 'settlement_offer',
            'category' => 'MARKETING',
        ]);

        WhatsappTemplateCache::create([
            'sid' => 'settlement_offer',
            'friendly_name' => 'settlement_offer',
            'category' => 'MARKETING',
            'meta_id' => '998877665544',
            'status' => 'APPROVED',
            'language' => 'en',
        ]);

        $client = \App\Models\Client::create([
            'name' => 'John Doe',
            'phone' => '+27820000001',
            'bank_id' => $bank->id,
        ]);

        CampaignWhatsappRecipient::create([
            'whatsapp_message_id' => $msg->id,
            'client_id' => $client->id,
            'status' => 'delivered',
            'phone' => '+27820000001',
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/meta-billing');

        $response->assertOk()
            ->assertJsonPath('meta_config.app_id', '347591848299284')
            ->assertJsonPath('meta_config.waba_id', '406811385845304')
            ->assertJsonPath('meta_config.waba_name', 'Iconis CRM')
            ->assertJsonPath('meta_config.status', 'ACTIVE')
            ->assertJsonPath('whatsapp_billing.summary.dispatched', '1')
            ->assertJsonPath('whatsapp_billing.summary.delivered', '1')
            ->assertJsonPath('whatsapp_billing.categories.marketing.rate', '$0.0175');
    }

    public function test_admin_can_update_ad_account_id(): void
    {
        $admin = $this->createSuperAdmin();

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/meta-billing/ad-account', [
            'ad_account_id' => '1234567890',
        ]);

        $response->assertOk()
            ->assertJsonPath('ad_account_id', '1234567890');

        $settings = SystemSetting::first();
        $this->assertEquals('act_1234567890', $settings->meta_ad_account_id);
    }

    public function test_unauthorized_user_cannot_update_ad_account_id(): void
    {
        $regularUser = User::factory()->create([
            'role' => 'STAFF',
        ]);

        Sanctum::actingAs($regularUser);
        $response = $this->postJson('/api/meta-billing/ad-account', [
            'ad_account_id' => '9999999999',
        ]);

        $response->assertForbidden();
    }

    public function test_sync_endpoint_refreshes_live_telemetry(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/406811385845304*' => Http::response([
                'id' => '406811385845304',
                'name' => 'Iconis CRM',
                'currency' => 'USD',
                'timezone_id' => '141',
                'status' => 'ACTIVE',
                'account_review_status' => 'APPROVED',
            ], 200),
            'https://graph.facebook.com/v21.0/347591848299284*' => Http::response([
                'id' => '347591848299284',
                'name' => 'CRM System API',
                'category' => 'Business',
            ], 200),
            'https://graph.facebook.com/v21.0/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'scopes' => ['whatsapp_business_management'],
                ],
            ], 200),
        ]);

        $admin = $this->createSuperAdmin();

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/meta-billing/sync', [
            'date_range' => 'this_month',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Meta billing and telemetry successfully synchronized.')
            ->assertJsonPath('data.meta_config.waba_id', '406811385845304');
    }

    public function test_ad_account_insights_pulled_when_permission_granted(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/406811385845304*' => Http::response([
                'id' => '406811385845304',
                'name' => 'Iconis CRM',
                'currency' => 'USD',
                'status' => 'ACTIVE',
            ], 200),
            'https://graph.facebook.com/v21.0/347591848299284*' => Http::response([
                'id' => '347591848299284',
                'name' => 'CRM System API',
            ], 200),
            'https://graph.facebook.com/v21.0/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'scopes' => ['whatsapp_business_management', 'ads_read', 'read_insights'],
                ],
            ], 200),
            'https://graph.facebook.com/v21.0/act_987654321/insights*' => Http::response([
                'data' => [
                    [
                        'spend' => '1250.50',
                        'impressions' => '45000',
                        'clicks' => '1200',
                        'reach' => '35000',
                        'ctr' => '2.67',
                        'cpc' => '1.04',
                        'cpm' => '27.79',
                    ],
                ],
            ], 200),
            'https://graph.facebook.com/v21.0/act_987654321*' => Http::response([
                'id' => 'act_987654321',
                'name' => 'Iconis Performance Ads',
                'currency' => 'USD',
                'amount_spent' => 254000,
            ], 200),
        ]);

        $admin = $this->createSuperAdmin();

        $settings = SystemSetting::first();
        $settings->meta_ad_account_id = 'act_987654321';
        $settings->save();

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/meta-billing?refresh=1');

        $response->assertOk()
            ->assertJsonPath('ad_account_insights.has_permission', true)
            ->assertJsonPath('ad_account_insights.permission_status', 'active')
            ->assertJsonPath('ad_account_insights.insights.spend', '$1,250.50')
            ->assertJsonPath('ad_account_insights.insights.impressions', '45,000')
            ->assertJsonPath('ad_account_insights.insights.clicks', '1,200')
            ->assertJsonPath('ad_account_insights.account_info.name', 'Iconis Performance Ads');
    }

    public function test_meta_billing_filters_by_bank(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/406811385845304*' => Http::response(['id' => '406811385845304', 'status' => 'ACTIVE'], 200),
            'https://graph.facebook.com/v21.0/347591848299284*' => Http::response(['id' => '347591848299284'], 200),
            'https://graph.facebook.com/v21.0/debug_token*' => Http::response(['data' => ['is_valid' => true, 'scopes' => []]], 200),
        ]);

        $admin = $this->createSuperAdmin();

        $bankA = Bank::create(['name' => 'Bank A', 'code' => 'BKA']);
        $bankB = Bank::create(['name' => 'Bank B', 'code' => 'BKB']);

        $c1 = Campaign::create(['name' => 'C1', 'bank_id' => $bankA->id, 'status' => 'Active', 'channels' => ['whatsapp']]);
        $c2 = Campaign::create(['name' => 'C2', 'bank_id' => $bankB->id, 'status' => 'Active', 'channels' => ['whatsapp']]);

        $m1 = CampaignWhatsappMessage::create(['campaign_id' => $c1->id, 'template_name' => 't1', 'template_sid' => 't1']);
        $m2 = CampaignWhatsappMessage::create(['campaign_id' => $c2->id, 'template_name' => 't2', 'template_sid' => 't2']);

        $clientA = \App\Models\Client::create(['name' => 'Client A', 'phone' => '+27820000010', 'bank_id' => $bankA->id]);
        $clientB = \App\Models\Client::create(['name' => 'Client B', 'phone' => '+27820000020', 'bank_id' => $bankB->id]);

        CampaignWhatsappRecipient::create(['whatsapp_message_id' => $m1->id, 'client_id' => $clientA->id, 'status' => 'delivered', 'phone' => '+27820000010']);
        CampaignWhatsappRecipient::create(['whatsapp_message_id' => $m2->id, 'client_id' => $clientB->id, 'status' => 'delivered', 'phone' => '+27820000020']);

        Sanctum::actingAs($admin);
        $resAll = $this->getJson('/api/meta-billing?bank_id=all')->assertOk();
        $this->assertEquals('2', $resAll->json('whatsapp_billing.summary.dispatched'));

        $resBankA = $this->getJson('/api/meta-billing?bank_id=' . $bankA->id)->assertOk();
        $this->assertEquals('1', $resBankA->json('whatsapp_billing.summary.dispatched'));
    }
}
