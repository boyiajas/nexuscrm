<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\ChatSession;
use App\Models\Client;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappNumberControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTemplateLockNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_filters_returns_template_locked_phone_numbers_and_waba_health_status(): void
    {
        SystemSetting::query()->create([
            'meta_environment' => 'sandbox',
            'live_chat_locked' => false,
            'live_chat_locked_message' => 'Emergency lock active.',
            'live_chat_template_locked_phone_numbers' => [
                [
                    'id' => '1029384756',
                    'phone_number_id' => '1029384756',
                    'display_phone_number' => '+27820000001',
                    'label' => '+27820000001 (Template Locked)',
                ],
            ],
        ]);

        $bank = Bank::query()->create([
            'name' => 'Bank A',
            'code' => 'bank-a',
            'status' => 'Active',
            'primary_whatsapp_number' => '+27820000001',
        ]);

        WhatsappNumberControl::query()->create([
            'phone_number_id' => '1029384756',
            'display_phone_number' => '+27820000001',
            'normalized_display_phone_number' => '27820000001',
            'quality_rating' => 'GREEN',
            'is_paused' => false,
        ]);

        $user = $this->createAdminUser($bank);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/chat/filters');

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('liveChatTemplateLockedPhoneNumbers', $data);
        $this->assertArrayHasKey('liveChatTemplateLockedIdentifiers', $data);
        $this->assertCount(1, $data['liveChatTemplateLockedPhoneNumbers']);
        $this->assertContains('1029384756', $data['liveChatTemplateLockedIdentifiers']);
        $this->assertContains('27820000001', $data['liveChatTemplateLockedIdentifiers']);

        // Check wabas list has health_status
        $this->assertNotEmpty($data['wabas']);
        $matchedWaba = collect($data['wabas'])->firstWhere('number', '+27820000001');
        $this->assertNotNull($matchedWaba);
        $this->assertEquals('GREEN', $matchedWaba['quality_rating']);
        $this->assertEquals('Good', $matchedWaba['health_status']);
    }

    public function test_settings_endpoint_saves_and_returns_template_locked_phone_numbers(): void
    {
        $bank = Bank::query()->create([
            'name' => 'Bank A',
            'code' => 'bank-a',
            'status' => 'Active',
            'primary_whatsapp_number' => '+27820000001',
        ]);

        $user = $this->createAdminUser($bank);
        Sanctum::actingAs($user);

        $payload = [
            'live_chat_locked' => false,
            'live_chat_locked_phone_numbers' => [],
            'live_chat_template_locked_phone_numbers' => json_encode([
                [
                    'id' => '1029384756',
                    'phone_number_id' => '1029384756',
                    'display_phone_number' => '+27820000001',
                    'label' => '+27820000001 (Template Locked Only)',
                ],
            ]),
        ];

        $response = $this->postJson('/api/settings', $payload);

        $response->assertOk();
        $settings = SystemSetting::first();
        $this->assertNotNull($settings);
        $this->assertCount(1, $settings->live_chat_template_locked_phone_numbers);
        $this->assertEquals('1029384756', $settings->live_chat_template_locked_phone_numbers[0]['id']);

        // Verify transformed settings via GET
        $getResponse = $this->getJson('/api/settings');
        $getResponse->assertOk();
        $this->assertCount(1, $getResponse->json('live_chat_template_locked_phone_numbers'));
    }

    public function test_template_sending_is_blocked_while_regular_chat_remains_allowed(): void
    {
        SystemSetting::query()->create([
            'meta_environment' => 'sandbox',
            'live_chat_locked' => false,
            'live_chat_locked_message' => 'Regular lock active.',
            'live_chat_template_locked_phone_numbers' => [
                [
                    'id' => '1029384756',
                    'phone_number_id' => '1029384756',
                    'display_phone_number' => '+27820000001',
                    'label' => '+27820000001 (Templates Only)',
                ],
            ],
            'disable_chat_for_opted_out_clients' => false,
        ]);

        $bank = Bank::query()->create([
            'name' => 'Bank A',
            'code' => 'bank-a',
            'status' => 'Active',
            'primary_whatsapp_number' => '+27820000001',
        ]);

        $user = $this->createAdminUser($bank);
        Sanctum::actingAs($user);

        $client = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Test Client',
            'phone' => '+27761112233',
            'opt_in' => 'yes',
        ]);

        // Internal session: verifies storeMessage is NOT blocked by template lock
        $sessionInternal = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'waba_phone_number_id' => '1029384756',
            'status' => 'active',
            'platform' => 'internal',
        ]);

        // 1. Regular chat message MUST succeed (201)
        $messageResponse = $this->postJson("/api/chat/sessions/{$sessionInternal->id}/messages", [
            'content' => 'Normal agent chat message',
        ]);

        $messageResponse->assertStatus(201);
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $sessionInternal->id,
            'content' => 'Normal agent chat message',
        ]);

        // WhatsApp session: verifies storeTemplateMessage IS blocked (403) by template lock
        $sessionWhatsapp = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'waba_phone_number_id' => '1029384756',
            'status' => 'active',
            'platform' => 'whatsapp',
        ]);

        // 2. Template message MUST be blocked (403)
        $templateResponse = $this->postJson("/api/chat/sessions/{$sessionWhatsapp->id}/templates", [
            'template_id' => 'HX1234567890',
            'variables' => [],
        ]);

        $templateResponse->assertStatus(403);
        $templateResponse->assertJson([
            'message' => 'Sending WhatsApp templates is currently locked for this WhatsApp number.',
        ]);
    }

    public function test_chat_session_show_includes_lock_and_health_flags(): void
    {
        SystemSetting::query()->create([
            'meta_environment' => 'sandbox',
            'live_chat_locked' => false,
            'live_chat_template_locked_phone_numbers' => [
                [
                    'id' => '1029384756',
                    'display_phone_number' => '+27820000001',
                ],
            ],
            'disable_chat_for_opted_out_clients' => false,
        ]);

        $bank = Bank::query()->create([
            'name' => 'Bank A',
            'code' => 'bank-a',
            'status' => 'Active',
            'primary_whatsapp_number' => '+27820000001',
        ]);

        WhatsappNumberControl::query()->create([
            'phone_number_id' => '1029384756',
            'display_phone_number' => '+27820000001',
            'normalized_display_phone_number' => '27820000001',
            'quality_rating' => 'GREEN',
            'is_paused' => false,
        ]);

        $user = $this->createAdminUser($bank);
        Sanctum::actingAs($user);

        $client = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Test Client',
            'phone' => '+27761112233',
            'opt_in' => 'yes',
        ]);

        $session = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'waba_phone_number_id' => '1029384756',
            'status' => 'active',
            'platform' => 'whatsapp',
        ]);

        $response = $this->getJson("/api/chat/sessions/{$session->id}");

        $response->assertOk();
        $this->assertFalse($response->json('is_live_chat_locked'));
        $this->assertTrue($response->json('is_template_locked'));
        $this->assertEquals('GREEN', $response->json('waba_quality_rating'));
        $this->assertEquals('Good', $response->json('waba_health_status'));
    }

    private function createAdminUser(Bank $bank): User
    {
        $role = Role::query()->where('code', User::ROLE_SUPER_ADMIN)->firstOrFail();

        $user = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin-' . Str::uuid() . '@example.test',
            'password' => Hash::make('Password123!'),
            'password_changed_at' => now(),
            'password_reset_required' => false,
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'bank_id' => $bank->id,
        ]);

        $user->roles()->sync([$role->id]);
        $user->banks()->sync([$bank->id]);

        return $user->fresh(['bank', 'banks', 'roles']);
    }
}
