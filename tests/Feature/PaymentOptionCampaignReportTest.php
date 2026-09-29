<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Campaign;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\ChatSession;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Services\CampaignWhatsappReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentOptionCampaignReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_chat_can_set_and_clear_the_linked_clients_payment_option(): void
    {
        [$user, $bank] = $this->createSuperAdminAndBank();

        $client = Client::query()->create([
            'name' => 'Payment Client',
            'phone' => '+27820000001',
            'bank_id' => $bank->id,
        ]);

        $session = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'status' => 'active',
            'platform' => 'whatsapp',
            'unread_count' => 0,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/chat/sessions/{$session->id}/payment-option", [
            'payment_option' => Client::PAYMENT_OPTION_DEBIT_ORDER,
        ])
            ->assertOk()
            ->assertJsonPath('payment_option', Client::PAYMENT_OPTION_DEBIT_ORDER);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'payment_option' => Client::PAYMENT_OPTION_DEBIT_ORDER,
        ]);
        $this->assertNotNull($client->fresh()->payment_option_updated_at);

        $this->postJson("/api/chat/sessions/{$session->id}/payment-option", [
            'payment_option' => 'none',
        ])
            ->assertOk()
            ->assertJsonPath('payment_option', null);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'payment_option' => null,
        ]);
    }

    public function test_analytics_campaign_report_counts_unique_replies_opt_outs_and_payment_options(): void
    {
        [$user, $bank] = $this->createSuperAdminAndBank();

        $campaign = Campaign::query()->create([
            'name' => 'September Collections',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        $clients = collect([
            ['name' => 'PTP Client', 'payment_option' => Client::PAYMENT_OPTION_PTP],
            ['name' => 'Debit Client', 'payment_option' => Client::PAYMENT_OPTION_DEBIT_ORDER],
            ['name' => 'Text Client', 'payment_option' => null],
            ['name' => 'No Reply Client', 'payment_option' => null],
            ['name' => 'Unsent Debit Client', 'payment_option' => Client::PAYMENT_OPTION_DEBIT_ORDER],
        ])->map(function (array $attributes, int $index) use ($bank) {
            return Client::query()->create($attributes + [
                'phone' => '+2782000001' . $index,
                'bank_id' => $bank->id,
            ]);
        });

        $campaign->clients()->sync($clients->pluck('id')->all());

        $firstMessage = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'created_by_user_id' => $user->id,
            'template_name' => 'payment_options',
            'sent_at' => now(),
            'total' => 4,
            'delivered' => 4,
            'failed' => 0,
            'pending' => 0,
        ]);

        $secondMessage = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'created_by_user_id' => $user->id,
            'template_name' => 'payment_options_follow_up',
            'sent_at' => now(),
            'total' => 1,
            'delivered' => 1,
            'failed' => 0,
            'pending' => 0,
        ]);

        $this->createRecipient($firstMessage, $clients[0], 'quick_reply', 'Pay now');
        $this->createRecipient($firstMessage, $clients[1], 'opt_out', 'Stop');
        $this->createRecipient($firstMessage, $clients[2], 'text', 'Please call me');
        $this->createRecipient($firstMessage, $clients[3]);
        $this->createRecipient($secondMessage, $clients[0], 'text', 'I will pay Friday');
        CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $firstMessage->id,
            'client_id' => $clients[4]->id,
            'phone' => $clients[4]->phone,
            'status' => 'Failed',
            'error_code' => '131000',
            'error_message' => 'Provider rejected the message.',
        ]);

        $draftCampaign = Campaign::query()->create([
            'name' => 'Unsent Draft Campaign',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);
        $draftCampaign->clients()->attach($clients[4]->id);
        $draftMessage = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $draftCampaign->id,
            'created_by_user_id' => $user->id,
            'template_name' => 'unsent_payment_options',
            'status' => 'Draft',
            'total' => 1,
            'delivered' => 0,
            'failed' => 0,
            'pending' => 0,
        ]);
        CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $draftMessage->id,
            'client_id' => $clients[4]->id,
            'phone' => $clients[4]->phone,
            'status' => 'Draft',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/campaigns/{$campaign->id}/stats")
            ->assertOk()
            ->assertJsonMissingPath('engagement_report');

        $response = $this->getJson('/api/analytics?timeframe=daily')->assertOk();
        $response
            ->assertJsonPath('summary.dispatched', '6')
            ->assertJsonPath('summary.delivered', '5');
        $campaignRows = collect($response->json('tables.campaigns'));

        $sentRow = $campaignRows->firstWhere('id', $campaign->id);
        $this->assertSame('6', $sentRow['sent']);
        $this->assertSame('83.3%', $sentRow['delivery']);
        $this->assertSame(3, $sentRow['replies']);
        $this->assertSame(2, $sentRow['quick_replies']);
        $this->assertSame(1, $sentRow['opt_outs']);
        $this->assertSame(1, $sentRow['ptp']);
        $this->assertSame(1, $sentRow['debit_order']);
        $this->assertSame(2, $sentRow['payment_not_set']);
        $this->assertSame('$0.04', $sentRow['cost']);
        $this->assertSame('N/A', $sentRow['recoveryPct']);
        $this->assertSame('Not tracked', $sentRow['recoveryAmt']);

        $draftRow = $campaignRows->firstWhere('id', $draftCampaign->id);
        $this->assertSame('0', $draftRow['sent']);
        $this->assertSame('0.0%', $draftRow['delivery']);
        $this->assertSame(0, $draftRow['replies']);
        $this->assertSame(0, $draftRow['quick_replies']);
        $this->assertSame(0, $draftRow['opt_outs']);
        $this->assertSame(0, $draftRow['ptp']);
        $this->assertSame(0, $draftRow['debit_order']);
        $this->assertSame(0, $draftRow['payment_not_set']);
        $this->assertSame('$0.00', $draftRow['cost']);
    }

    public function test_campaign_report_reads_legacy_top_level_quick_reply_and_opt_out_payloads(): void
    {
        [$user, $bank] = $this->createSuperAdminAndBank();
        $campaign = Campaign::query()->create([
            'name' => 'Legacy Reply Campaign',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);
        $message = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'created_by_user_id' => $user->id,
            'template_name' => 'legacy_buttons',
            'sent_at' => now(),
        ]);
        $quickReplyClient = Client::query()->create([
            'name' => 'Legacy Button Client',
            'phone' => '+27821110001',
            'bank_id' => $bank->id,
        ]);
        $optOutClient = Client::query()->create([
            'name' => 'Legacy Opt Out Client',
            'phone' => '+27821110002',
            'bank_id' => $bank->id,
        ]);

        CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $message->id,
            'client_id' => $quickReplyClient->id,
            'phone' => $quickReplyClient->phone,
            'status' => 'Delivered',
            'last_response' => 'pay now',
            'provider_status_payload' => [
                'messages' => [[
                    'interactive' => [
                        'type' => 'button_reply',
                        'button_reply' => ['id' => 'pay_now', 'title' => 'Pay now'],
                    ],
                ]],
            ],
        ]);
        CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $message->id,
            'client_id' => $optOutClient->id,
            'phone' => $optOutClient->phone,
            'status' => 'Delivered',
            'last_response' => 'stop',
            'provider_status_payload' => [
                'messages' => [[
                    'text' => ['body' => 'Stop'],
                ]],
            ],
        ]);

        $report = app(CampaignWhatsappReportService::class)->build($campaign);

        $this->assertSame(2, $report['messages_sent']);
        $this->assertSame(2, $report['clients_replied']);
        $this->assertSame(1, $report['quick_reply_clients']);
        $this->assertSame(1, $report['opt_out_clients']);
    }

    private function createRecipient(
        CampaignWhatsappMessage $message,
        Client $client,
        ?string $replyType = null,
        ?string $replyLabel = null
    ): CampaignWhatsappRecipient {
        return CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $message->id,
            'client_id' => $client->id,
            'phone' => $client->phone,
            'status' => 'Delivered',
            'last_response' => $replyLabel,
            'last_response_at' => $replyType ? now() : null,
            'reply_type' => $replyType,
            'reply_label' => $replyLabel,
            'reply_source' => in_array($replyType, ['quick_reply', 'opt_out'], true)
                ? 'interactive.button_reply'
                : $replyType,
        ]);
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
