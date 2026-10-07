<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppServiceInterface;
use App\Jobs\ProcessCampaignWhatsappRecipientJob;
use App\Models\Bank;
use App\Models\Campaign;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\Client;
use App\Services\WhatsAppBatchService;
use App\Services\WhatsAppDailyLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CampaignWhatsappFailedOptOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_send_automatically_marks_client_opt_in_as_no(): void
    {
        $recipient = $this->createRecipient('Queued');
        $client = $recipient->client;
        $client->update(['opt_in' => 'yes', 'whatsapp_opted_in_at' => now(), 'whatsapp_opted_out_at' => null]);

        $meta = Mockery::mock(WhatsAppServiceInterface::class);
        $meta->shouldReceive('sendTemplateFromSubjectMessage')
            ->once()
            ->andThrow(new \RuntimeException('Meta API error [400]: (#131026) Message undeliverable'));

        $job = new ProcessCampaignWhatsappRecipientJob($recipient->id);
        $job->handle(
            $meta,
            app(WhatsAppDailyLimitService::class),
            app(WhatsAppBatchService::class)
        );

        $client->refresh();
        $recipient->refresh();

        $this->assertSame('Failed', $recipient->status);
        $this->assertSame('no', $client->opt_in);
        $this->assertNotNull($client->whatsapp_opted_out_at);
        $this->assertStringContainsString('WhatsApp send failed', $client->whatsapp_opt_out_reason);
        $this->assertTrue($client->isWhatsappSuppressed());
    }

    public function test_missing_phone_automatically_marks_client_opt_in_as_no(): void
    {
        $recipient = $this->createRecipient('Queued', null);
        $client = $recipient->client;
        $client->update([
            'phone' => null,
            'cell_phone' => null,
            'home_phone' => null,
            'work_phone' => null,
            'opt_in' => 'yes',
        ]);

        $meta = Mockery::mock(WhatsAppServiceInterface::class);
        $meta->shouldNotReceive('sendTemplateFromSubjectMessage');

        $job = new ProcessCampaignWhatsappRecipientJob($recipient->id);
        $job->handle(
            $meta,
            app(WhatsAppDailyLimitService::class),
            app(WhatsAppBatchService::class)
        );

        $client->refresh();
        $recipient->refresh();

        $this->assertSame('No Phone', $recipient->status);
        $this->assertSame('no', $client->opt_in);
        $this->assertNotNull($client->whatsapp_opted_out_at);
        $this->assertStringContainsString('no valid phone number', $client->whatsapp_opt_out_reason);
    }

    public function test_webhook_failed_status_automatically_marks_client_opt_in_as_no(): void
    {
        $recipient = $this->createRecipient('Sent', '+27821112233');
        $recipient->update([
            'provider_message_id' => 'wamid.HBgLMjcwNzE4Mzgx',
        ]);
        $client = $recipient->client;
        $client->update(['opt_in' => 'yes', 'whatsapp_opted_in_at' => now(), 'whatsapp_opted_out_at' => null]);

        $webhookPayload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '27871234567',
                                    'phone_number_id' => '100000000000001',
                                ],
                                'statuses' => [
                                    [
                                        'id' => 'wamid.HBgLMjcwNzE4Mzgx',
                                        'status' => 'failed',
                                        'timestamp' => (string) now()->timestamp,
                                        'recipient_id' => '27821112233',
                                        'errors' => [
                                            [
                                                'code' => 131026,
                                                'title' => 'Message undeliverable',
                                                'message' => 'Message undeliverable to this destination',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $meta = Mockery::mock(WhatsAppServiceInterface::class);
        $meta->shouldReceive('appSecret')->andReturn(null);
        $this->app->instance(WhatsAppServiceInterface::class, $meta);

        $response = $this->postJson('/api/whatsapp/webhook', $webhookPayload);
        $response->assertOk();

        $client->refresh();
        $recipient->refresh();

        $this->assertSame('Failed', $recipient->status);
        $this->assertSame('no', $client->opt_in);
        $this->assertNotNull($client->whatsapp_opted_out_at);
        $this->assertStringContainsString('131026', $client->whatsapp_opt_out_reason);
        $this->assertStringContainsString('Message undeliverable', $client->whatsapp_opt_out_reason);
    }

    public function test_job_failure_callback_marks_client_opt_in_as_no(): void
    {
        $recipient = $this->createRecipient('Processing');
        $client = $recipient->client;
        $client->update(['opt_in' => 'yes']);

        $job = new ProcessCampaignWhatsappRecipientJob($recipient->id);
        $job->failed(new \RuntimeException('Queue timeout exceeded'));

        $client->refresh();
        $recipient->refresh();

        $this->assertSame('Failed', $recipient->status);
        $this->assertSame('no', $client->opt_in);
        $this->assertNotNull($client->whatsapp_opted_out_at);
        $this->assertStringContainsString('Queue timeout exceeded', $client->whatsapp_opt_out_reason);
    }

    private function createRecipient(string $status, ?string $phone = '+27821112233'): CampaignWhatsappRecipient
    {
        $bank = Bank::query()->create([
            'name' => 'Bank ' . uniqid(),
            'code' => uniqid('bank'),
            'status' => 'Active',
        ]);

        $campaign = Campaign::query()->create([
            'name' => 'Test Campaign',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        $message = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'template_sid' => 'test_template',
            'sent_at' => now(),
            'status' => 'Processing',
            'queued_at' => now(),
        ]);

        $client = Client::query()->create([
            'name' => 'Test Debtor',
            'phone' => $phone,
            'bank_id' => $bank->id,
            'whatsapp_contact_basis' => 'bank_instruction',
            'opt_in' => 'yes',
        ]);

        return CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $message->id,
            'client_id' => $client->id,
            'phone' => $phone,
            'status' => $status,
        ]);
    }
}
