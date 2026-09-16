<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppServiceInterface;
use App\Models\Bank;
use App\Models\Campaign;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Client;
use App\Models\Department;
use App\Models\User;
use App\Models\WhatsAppFlow;
use App\Services\MetaWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsAppFlowGreetingTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private Department $dept;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = Bank::query()->create([
            'name' => 'African Bank',
            'code' => 'absa',
            'status' => 'Active',
        ]);

        $this->dept = Department::query()->create([
            'name' => 'Collections',
            'code' => 'coll',
            'status' => 'Active',
            'bank_id' => $this->bank->id,
        ]);

        $this->user = User::factory()->create([
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
        ]);
    }

    public function test_first_client_reply_triggers_automated_greeting_and_sets_flow_step(): void
    {
        $mockMeta = Mockery::mock(MetaWhatsAppService::class);
        $mockMeta->shouldReceive('appSecret')->andReturn(null);
        // Expect sendTextMessage with the Greeting message
        $mockMeta->shouldReceive('sendTextMessage')
            ->once()
            ->with(
                '27821112233',
                'Thank you for contacting Strauss Daly Attorneys. Please provide me with your ID number to assist you further.',
                Mockery::any()
            )
            ->andReturn(['messages' => [['id' => 'wamid.HBg...']]]);

        $this->app->instance(MetaWhatsAppService::class, $mockMeta);
        $this->app->instance(WhatsAppServiceInterface::class, $mockMeta);

        $client = Client::query()->create([
            'name' => 'John Doe',
            'phone' => '+27821112233',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
        ]);

        $campaign = Campaign::query()->create([
            'name' => 'Test Campaign',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
            'created_by' => $this->user->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        $flowDef = [
            [
                'id' => 'greeting',
                'label' => 'Greeting',
                'message' => 'Thank you for contacting Strauss Daly Attorneys. Please provide me with your ID number to assist you further.',
                'decision' => false,
            ],
            [
                'id' => 'verification',
                'label' => 'Verification',
                'message' => 'Thank you, we have verified your ID.',
                'decision' => false,
            ],
        ];

        $batch = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'created_by_user_id' => $this->user->id,
            'mode' => 'flow',
            'template_sid' => 'payment_instructions',
            'flow_definition' => $flowDef,
            'track_responses' => true,
            'enable_live_chat' => true,
        ]);

        $recipient = CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $batch->id,
            'client_id' => $client->id,
            'phone' => '+27821112233',
            'status' => 'Delivered',
            'current_flow_step_id' => null,
        ]);

        $payload = [
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '27614774098',
                                    'phone_number_id' => '10987654321',
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'John Doe'], 'wa_id' => '27821112233'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '27821112233',
                                        'id' => 'wamid.inbound.1',
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => 'Good day, I received your message'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        $response->assertOk();

        $recipient->refresh();
        $this->assertEquals('greeting', $recipient->current_flow_step_id);
        $this->assertEquals('good day, i received your message', strtolower($recipient->last_response));

        // Verify ChatMessage logged the outbound automated greeting in ChatSession
        $session = ChatSession::where('client_id', $client->id)->first();
        $this->assertNotNull($session);

        $messages = $session->messages()->orderBy('id')->get();
        $this->assertCount(2, $messages);
        $this->assertEquals('client', $messages[0]->sender);
        $this->assertEquals('Good day, I received your message', $messages[0]->content);

        $this->assertEquals('agent', $messages[1]->sender);
        $this->assertEquals('Thank you for contacting Strauss Daly Attorneys. Please provide me with your ID number to assist you further.', $messages[1]->content);
    }

    public function test_subsequent_client_reply_advances_flow_step(): void
    {
        $mockMeta = Mockery::mock(MetaWhatsAppService::class);
        $mockMeta->shouldReceive('appSecret')->andReturn(null);
        // Expect sendTextMessage with the second step (Verification)
        $mockMeta->shouldReceive('sendTextMessage')
            ->once()
            ->with(
                '27821112233',
                'Thank you, we have verified your ID.',
                Mockery::any()
            )
            ->andReturn(['messages' => [['id' => 'wamid.HBg...']]]);

        $this->app->instance(MetaWhatsAppService::class, $mockMeta);
        $this->app->instance(WhatsAppServiceInterface::class, $mockMeta);

        $client = Client::query()->create([
            'name' => 'John Doe',
            'phone' => '+27821112233',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
        ]);

        $campaign = Campaign::query()->create([
            'name' => 'Test Campaign',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
            'created_by' => $this->user->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        $flowDef = [
            [
                'id' => 'greeting',
                'label' => 'Greeting',
                'message' => 'Thank you for contacting Strauss Daly Attorneys. Please provide me with your ID number to assist you further.',
                'decision' => false,
            ],
            [
                'id' => 'verification',
                'label' => 'Verification',
                'message' => 'Thank you, we have verified your ID.',
                'decision' => false,
            ],
        ];

        $batch = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'created_by_user_id' => $this->user->id,
            'mode' => 'flow',
            'template_sid' => 'payment_instructions',
            'flow_definition' => $flowDef,
            'track_responses' => true,
            'enable_live_chat' => true,
        ]);

        $recipient = CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $batch->id,
            'client_id' => $client->id,
            'phone' => '+27821112233',
            'status' => 'Delivered',
            'current_flow_step_id' => 'greeting', // Already got greeting!
        ]);

        $payload = [
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '27614774098',
                                    'phone_number_id' => '10987654321',
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'John Doe'], 'wa_id' => '27821112233'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '27821112233',
                                        'id' => 'wamid.inbound.2',
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => '9605040245083'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        $response->assertOk();

        $recipient->refresh();
        $this->assertEquals('verification', $recipient->current_flow_step_id);
    }

    public function test_opt_out_reply_does_not_trigger_flow_and_sets_opt_in_no(): void
    {
        $mockMeta = Mockery::mock(MetaWhatsAppService::class);
        $mockMeta->shouldReceive('appSecret')->andReturn(null);
        // sendTextMessage should NEVER be called on opt-out!
        $mockMeta->shouldNotReceive('sendTextMessage');

        $this->app->instance(MetaWhatsAppService::class, $mockMeta);
        $this->app->instance(WhatsAppServiceInterface::class, $mockMeta);

        $client = Client::query()->create([
            'name' => 'Opt Out Client',
            'phone' => '+27821112233',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
            'opt_in' => 'yes',
        ]);

        $campaign = Campaign::query()->create([
            'name' => 'Test Campaign',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
            'created_by' => $this->user->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        $flowDef = [
            [
                'id' => 'greeting',
                'label' => 'Greeting',
                'message' => 'Thank you for contacting us. Please provide your ID number.',
                'decision' => false,
            ],
        ];

        $batch = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'created_by_user_id' => $this->user->id,
            'mode' => 'flow',
            'template_sid' => 'payment_instructions',
            'flow_definition' => $flowDef,
            'track_responses' => true,
            'enable_live_chat' => true,
        ]);

        $recipient = CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $batch->id,
            'client_id' => $client->id,
            'phone' => '+27821112233',
            'status' => 'Delivered',
            'current_flow_step_id' => null,
        ]);

        $payload = [
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '27614774098',
                                    'phone_number_id' => '10987654321',
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'Opt Out Client'], 'wa_id' => '27821112233'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '27821112233',
                                        'id' => 'wamid.inbound.3',
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => 'STOP'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        $response->assertOk();

        $client->refresh();
        $this->assertEquals('no', $client->opt_in);

        $recipient->refresh();
        $this->assertNull($recipient->current_flow_step_id);
    }

    public function test_template_mode_campaign_does_not_trigger_flow_greeting_only_flow_tab_does(): void
    {
        $mockMeta = Mockery::mock(MetaWhatsAppService::class);
        $mockMeta->shouldReceive('appSecret')->andReturn(null);
        // Automated flow greeting should NOT be sent when message was sent from template tab (mode = 'template')!
        $mockMeta->shouldNotReceive('sendTextMessage');

        $this->app->instance(MetaWhatsAppService::class, $mockMeta);
        $this->app->instance(WhatsAppServiceInterface::class, $mockMeta);

        $client = Client::query()->create([
            'name' => 'Jane Doe',
            'phone' => '+27821112233',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
        ]);

        $campaign = Campaign::query()->create([
            'name' => 'Test Campaign',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
            'created_by' => $this->user->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        // Create active flow for template_sid = 'standard_template_1'
        WhatsAppFlow::query()->create([
            'name' => 'Standard Flow Greeting',
            'template_sid' => 'standard_template_1',
            'template_name' => 'standard_template_1',
            'status' => 'active',
            'flow_definition' => [
                [
                    'id' => 'greeting_tpl',
                    'label' => 'Greeting',
                    'message' => 'Hello from the active template flow greeting!',
                    'decision' => false,
                ],
            ],
            'created_by' => $this->user->id,
        ]);

        $batch = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'created_by_user_id' => $this->user->id,
            'mode' => 'template', // mode is template (sent from Template tab)!
            'template_sid' => 'standard_template_1',
            'track_responses' => true,
            'enable_live_chat' => true,
        ]);

        $recipient = CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $batch->id,
            'client_id' => $client->id,
            'phone' => '+27821112233',
            'status' => 'Delivered',
            'current_flow_step_id' => null,
        ]);

        $payload = [
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '27614774098',
                                    'phone_number_id' => '10987654321',
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'Jane Doe'], 'wa_id' => '27821112233'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '27821112233',
                                        'id' => 'wamid.inbound.4',
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => 'I would like more information'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        $response->assertOk();

        $recipient->refresh();
        $this->assertNull($recipient->current_flow_step_id);
    }
}
