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
use App\Models\WhatsappTemplateCache;
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

    public function test_flow_step_can_send_an_approved_template_with_client_variables(): void
    {
        $mockMeta = Mockery::mock(MetaWhatsAppService::class);
        $mockMeta->shouldReceive('appSecret')->andReturn(null);
        $mockMeta->shouldNotReceive('sendTextMessage');
        $mockMeta->shouldReceive('sendTemplateFromSubjectMessage')
            ->once()
            ->with(
                '27821112233',
                'flow_follow_up',
                '',
                '',
                ['body_1' => 'John', 'body_2' => 'Flow Campaign'],
                '10987654321'
            )
            ->andReturn([
                'message_id' => 'wamid.flow.template.1',
                'status' => 'accepted',
            ]);

        $this->app->instance(MetaWhatsAppService::class, $mockMeta);
        $this->app->instance(WhatsAppServiceInterface::class, $mockMeta);

        $client = Client::query()->create([
            'name' => 'John Doe',
            'first_name' => 'John',
            'phone' => '+27821112233',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
        ]);

        $campaign = Campaign::query()->create([
            'name' => 'Flow Campaign',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
            'created_by' => $this->user->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        $batch = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'created_by_user_id' => $this->user->id,
            'mode' => 'flow',
            'template_sid' => 'initial_template',
            'flow_definition' => [[
                'id' => 'follow_up',
                'label' => 'Follow Up',
                'reply_type' => 'template',
                'message' => '',
                'template_sid' => 'flow_follow_up',
                'template_name' => 'flow_follow_up',
                'template_preview' => 'Hello {{1}}, welcome to {{2}}.',
                'template_variables' => [
                    'body_1' => ['source' => 'client.first_name', 'custom_value' => ''],
                    'body_2' => ['source' => 'campaign.name', 'custom_value' => ''],
                ],
                'decision' => false,
            ]],
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

        $response = $this->postJson('/api/whatsapp/webhook', [
            'entry' => [[
                'id' => '123456789',
                'changes' => [[
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
                        'messages' => [[
                            'from' => '27821112233',
                            'id' => 'wamid.inbound.template.1',
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => 'Hello'],
                        ]],
                    ],
                ]],
            ]],
        ]);

        $response->assertOk();

        $recipient->refresh();
        $this->assertSame('follow_up', $recipient->current_flow_step_id);

        $outbound = ChatMessage::query()
            ->where('sender', 'agent')
            ->where('provider_message_id', 'wamid.flow.template.1')
            ->first();

        $this->assertNotNull($outbound);
        $this->assertTrue($outbound->is_template);
        $this->assertSame('Hello John, welcome to Flow Campaign.', $outbound->content);
        $this->assertSame('accepted', $outbound->delivery_status);
    }

    public function test_flow_editor_saves_canonical_approved_template_step_details(): void
    {
        $superAdminRole = \App\Models\Role::query()->where('code', User::ROLE_SUPER_ADMIN)->firstOrFail();
        $this->user->roles()->sync([$superAdminRole->id]);
        $this->user->update([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);

        WhatsappTemplateCache::query()->create([
            'sid' => 'flow_follow_up',
            'friendly_name' => 'Flow Follow Up',
            'language' => 'en',
            'category' => 'UTILITY',
            'status' => 'approved',
            'header_text' => 'Account update',
            'body_preview' => 'Hello {{1}}, your account is {{2}}.',
            'footer_text' => 'Thank you',
            'variables' => [
                'body_1' => 'Body Variable 1',
                'body_2' => 'Body Variable 2',
            ],
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->user->fresh());

        $response = $this->postJson('/api/whatsapp-flows', [
            'name' => 'Template Step Flow',
            'template_sid' => 'initial_template',
            'template_name' => 'Initial Template',
            'template_language' => 'en',
            'status' => 'active',
            'flow_definition' => [[
                'id' => 'follow_up',
                'label' => 'Follow Up',
                'reply_type' => 'template',
                'template_sid' => 'flow_follow_up',
                'template_variables' => [
                    'body_1' => ['source' => 'client.first_name', 'custom_value' => 'ignored'],
                    'body_2' => ['source' => 'custom', 'custom_value' => 'active'],
                ],
                'decision' => false,
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('flow_definition.0.reply_type', 'template')
            ->assertJsonPath('flow_definition.0.template_name', 'Flow Follow Up')
            ->assertJsonPath('flow_definition.0.template_preview', 'Hello {{1}}, your account is {{2}}.')
            ->assertJsonPath('flow_definition.0.template_variables.body_1.source', 'client.first_name')
            ->assertJsonPath('flow_definition.0.template_variables.body_1.custom_value', '')
            ->assertJsonPath('flow_definition.0.template_variables.body_2.custom_value', 'active');
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

    public function test_flow_batch_saves_template_variables(): void
    {
        $superAdminRole = \App\Models\Role::query()->where('code', User::ROLE_SUPER_ADMIN)->firstOrFail();
        $this->user->roles()->sync([$superAdminRole->id]);
        $this->user->update([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);
        $this->user->refresh();

        $mockMeta = Mockery::mock(MetaWhatsAppService::class);
        $mockMeta->shouldReceive('appSecret')->andReturn(null);
        $mockMeta->shouldReceive('resolveSenderContext')->andReturn([
            'phone_number_id' => '10987654321',
            'display_phone_number' => '+27821112233',
        ]);
        $mockMeta->shouldReceive('getTemplateDetails')
            ->with('55_settlement_offer')
            ->andReturn([
                'name' => '55_settlement_offer',
                'variables' => [
                    'body_1' => 'Body Variable 1',
                    'body_2' => 'Body Variable 2',
                ],
                'components' => [
                    ['type' => 'BODY', 'text' => 'Hello {{1}}, balance {{2}}'],
                ],
            ]);

        $this->app->instance(MetaWhatsAppService::class, $mockMeta);
        $this->app->instance(WhatsAppServiceInterface::class, $mockMeta);

        $client = Client::query()->create([
            'name' => 'John Doe',
            'phone' => '+27821112233',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
            'whatsapp_contact_basis' => 'consent',
        ]);

        $campaign = Campaign::query()->create([
            'name' => 'Flow Campaign',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
            'created_by' => $this->user->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
            'whatsapp_from' => '+27821112233',
        ]);
        $campaign->clients()->attach($client->id);

        $flow = WhatsAppFlow::query()->create([
            'name' => 'Test Flow',
            'template_name' => '55_settlement_offer',
            'template_sid' => '55_settlement_offer',
            'flow_definition' => [
                ['id' => 'greeting', 'message' => 'Greeting text'],
            ],
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/campaigns/{$campaign->id}/whatsapp-messages", [
            'mode' => 'flow',
            'flow_id' => $flow->id,
            'clients_mode' => 'all',
            'template_variables' => [
                '1' => ['source' => 'client.first_name', 'custom_value' => ''],
                'body_2' => ['source' => 'custom', 'custom_value' => '1000'],
            ],
            'send_now' => false,
        ]);

        $response->assertCreated();

        $message = CampaignWhatsappMessage::where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($message);
        $this->assertEquals('flow', $message->mode);
        $this->assertEquals('55_settlement_offer', $message->template_sid);
        $this->assertNotNull($message->template_variables);
        $this->assertEquals('client.first_name', $message->template_variables['body_1']['source']);
        $this->assertEquals('custom', $message->template_variables['body_2']['source']);
        $this->assertEquals('1000', $message->template_variables['body_2']['custom_value']);
    }

    public function test_flow_batch_updates_template_variables(): void
    {
        $superAdminRole = \App\Models\Role::query()->where('code', User::ROLE_SUPER_ADMIN)->firstOrFail();
        $this->user->roles()->sync([$superAdminRole->id]);
        $this->user->update([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);
        $this->user->refresh();

        $mockMeta = Mockery::mock(MetaWhatsAppService::class);
        $mockMeta->shouldReceive('appSecret')->andReturn(null);
        $mockMeta->shouldReceive('resolveSenderContext')->andReturn([
            'phone_number_id' => '10987654321',
            'display_phone_number' => '+27821112233',
        ]);
        $mockMeta->shouldReceive('getTemplateDetails')
            ->with('55_settlement_offer')
            ->andReturn([
                'name' => '55_settlement_offer',
                'variables' => [
                    'body_1' => 'Body Variable 1',
                    'body_2' => 'Body Variable 2',
                ],
                'components' => [
                    ['type' => 'BODY', 'text' => 'Hello {{1}}, balance {{2}}'],
                ],
            ]);

        $this->app->instance(MetaWhatsAppService::class, $mockMeta);
        $this->app->instance(WhatsAppServiceInterface::class, $mockMeta);

        $client = Client::query()->create([
            'name' => 'John Doe',
            'phone' => '+27821112233',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
            'whatsapp_contact_basis' => 'consent',
        ]);

        $campaign = Campaign::query()->create([
            'name' => 'Flow Campaign',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
            'created_by' => $this->user->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
            'whatsapp_from' => '+27821112233',
        ]);
        $campaign->clients()->attach($client->id);

        $flow = WhatsAppFlow::query()->create([
            'name' => 'Test Flow',
            'template_name' => '55_settlement_offer',
            'template_sid' => '55_settlement_offer',
            'flow_definition' => [
                ['id' => 'greeting', 'message' => 'Greeting text'],
            ],
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $batch = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'mode' => 'flow',
            'template_sid' => $flow->template_sid,
            'whatsapp_flow_id' => $flow->id,
            'flow_name' => $flow->name,
            'template_name' => $flow->name,
            'name' => $flow->name,
            'preview_body' => 'Greeting text',
            'total_recipients' => 1,
            'pending' => 1,
            'created_by' => $this->user->id,
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->user);

        $response = $this->putJson("/api/campaigns/{$campaign->id}/whatsapp-messages/{$batch->id}", [
            'mode' => 'flow',
            'flow_id' => $flow->id,
            'clients_mode' => 'all',
            'template_variables' => [
                'body_1' => ['source' => 'client.first_name', 'custom_value' => ''],
                'body_2' => ['source' => 'client.outstanding_balance', 'custom_value' => ''],
            ],
            'send_now' => false,
        ]);

        $response->assertOk();

        $batch->refresh();
        $this->assertEquals('client.first_name', $batch->template_variables['body_1']['source']);
        $this->assertEquals('client.outstanding_balance', $batch->template_variables['body_2']['source']);
    }

    public function test_quick_reply_button_with_context_id_triggers_greeting_when_track_responses_is_false(): void
    {
        $mockMeta = Mockery::mock(MetaWhatsAppService::class);
        $mockMeta->shouldReceive('appSecret')->andReturn(null);
        $mockMeta->shouldReceive('sendTextMessage')
            ->once()
            ->with(
                '27842575612',
                'Thank you for contacting Strauss Daly Attorneys. Please provide me with your ID number to assist you further.',
                Mockery::any()
            )
            ->andReturn(['messages' => [['id' => 'wamid.auto.greeting.1']]]);

        $this->app->instance(MetaWhatsAppService::class, $mockMeta);
        $this->app->instance(WhatsAppServiceInterface::class, $mockMeta);

        $client = Client::query()->create([
            'name' => 'Taiwo Peter Ajakaiye',
            'phone' => '+27842575612',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
        ]);

        $campaign = Campaign::query()->create([
            'name' => 'Flow Campaign Quick Reply',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
            'created_by' => $this->user->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
            'whatsapp_from' => '+27614776401',
        ]);

        $flow = WhatsAppFlow::query()->create([
            'name' => '55 Settlement Flow',
            'template_name' => '55_settlement_offer',
            'template_sid' => '55_settlement_offer',
            'flow_definition' => [
                [
                    'id' => 'greeting',
                    'label' => 'Greeting',
                    'message' => 'Thank you for contacting Strauss Daly Attorneys. Please provide me with your ID number to assist you further.',
                    'decision' => false,
                ],
                [
                    'id' => 'id_request',
                    'label' => 'ID Request',
                    'message' => 'Please enter your ID number.',
                    'decision' => false,
                ],
            ],
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        // Simulating batch where track_responses is false and flow_definition is null on batch (only on WhatsAppFlow model)
        $batch = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'created_by_user_id' => $this->user->id,
            'mode' => 'flow',
            'whatsapp_flow_id' => $flow->id,
            'flow_name' => $flow->name,
            'template_sid' => '55_settlement_offer',
            'flow_definition' => null,
            'track_responses' => false,
            'enable_live_chat' => true,
        ]);

        $sentTemplateWamid = 'wamid.HBgLMjc4NDI1NzU2MTIVAgARGBJCOEI2MUJCNkY1QTA0MUVENDkA';

        $recipient = CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $batch->id,
            'client_id' => $client->id,
            'phone' => '+27842575612',
            'provider_message_id' => $sentTemplateWamid,
            'status' => 'Delivered',
            'current_flow_step_id' => null,
            'last_response' => null,
        ]);

        $payload = [
            'entry' => [
                [
                    'id' => '1455412218881488',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '27614776401',
                                    'phone_number_id' => '1247262038476724',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Taiwo Peter Ajakaiye'],
                                        'wa_id' => '27842575612',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'context' => [
                                            'from' => '27614776401',
                                            'id' => $sentTemplateWamid,
                                        ],
                                        'from' => '27842575612',
                                        'id' => 'wamid.HBgLMjc4NDI1NzU2MTIVAgASGCBBQzIyQzVBNEFERkIxMUNDNERDOTlERDZGRjdBQ0I5QwA=',
                                        'timestamp' => (string) time(),
                                        'type' => 'button',
                                        'button' => [
                                            'payload' => 'quick reply',
                                            'text' => 'Quick Reply',
                                        ],
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
        $this->assertEquals('quick reply', strtolower($recipient->last_response));

        $session = ChatSession::where('client_id', $client->id)->first();
        $this->assertNotNull($session);

        $messages = $session->messages()->orderBy('id')->get();
        $this->assertCount(2, $messages);
        $this->assertEquals('client', $messages[0]->sender);
        $this->assertEquals('Quick Reply', $messages[0]->content);
        $this->assertEquals('agent', $messages[1]->sender);
        $this->assertEquals('Thank you for contacting Strauss Daly Attorneys. Please provide me with your ID number to assist you further.', $messages[1]->content);
    }
}
