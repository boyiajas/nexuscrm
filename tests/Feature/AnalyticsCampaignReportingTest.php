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
