<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\ChatSession;
use App\Models\Client;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatEmergencyLockNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_filters_returns_locked_phone_numbers_and_identifiers(): void
    {
        SystemSetting::query()->create([
            'live_chat_locked' => false,
            'live_chat_locked_message' => 'Selective emergency lock is active.',
            'live_chat_locked_phone_numbers' => [
                [
                    'id' => '1029384756',
                    'phone_number_id' => '1029384756',
                    'display_phone_number' => '+27820000001',
                    'label' => '+27820000001 (Bank A)',
                ],
            ],
        ]);

        $bank = Bank::query()->create([
            'name' => 'Bank A',
            'code' => 'bank-a',
            'status' => 'Active',
        ]);

        $user = $this->createAdminUser($bank);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/chat/filters');

        $response->assertOk();
        $response->assertJson([
            'liveChatLocked' => false,
            'liveChatLockedMessage' => 'Selective emergency lock is active.',
        ]);

        $data = $response->json();
        $this->assertCount(1, $data['liveChatLockedPhoneNumbers']);
        $this->assertContains('1029384756', $data['liveChatLockedIdentifiers']);
        $this->assertContains('27820000001', $data['liveChatLockedIdentifiers']);
    }

    public function test_settings_endpoint_saves_and_returns_locked_phone_numbers(): void
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
            'live_chat_locked_message' => 'Messaging is locked for selected numbers.',
            'live_chat_locked_phone_numbers' => json_encode([
                [
                    'id' => '1029384756',
                    'phone_number_id' => '1029384756',
                    'display_phone_number' => '+27820000001',
                    'label' => '+27820000001 (Bank A - Primary)',
                ],
            ]),
        ];

        $response = $this->postJson('/api/settings', $payload);

        $response->assertOk();
        $response->assertJson([
            'live_chat_locked' => false,
            'live_chat_locked_message' => 'Messaging is locked for selected numbers.',
        ]);

        $settings = SystemSetting::first();
        $this->assertNotNull($settings);
        $this->assertCount(1, $settings->live_chat_locked_phone_numbers);
        $this->assertEquals('1029384756', $settings->live_chat_locked_phone_numbers[0]['id']);

        // Verify available numbers route
        $availResponse = $this->getJson('/api/settings/whatsapp-numbers');
        $availResponse->assertOk();
        $this->assertNotEmpty($availResponse->json('numbers'));
    }

    public function test_message_sending_blocked_for_session_matching_locked_number(): void
    {
        SystemSetting::query()->create([
            'live_chat_locked' => false,
            'live_chat_locked_message' => 'Emergency lock: messaging disabled on this line.',
            'live_chat_locked_phone_numbers' => [
                [
                    'id' => '1029384756',
                    'phone_number_id' => '1029384756',
                    'display_phone_number' => '+27820000001',
                    'label' => '+27820000001 (Locked Line)',
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
            'name' => 'Active Client',
            'phone' => '+27761112233',
            'opt_in' => 'yes',
        ]);

        // Session associated with the locked WABA phone number ID
        $sessionLocked = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'waba_phone_number_id' => '1029384756',
            'status' => 'active',
            'platform' => 'internal',
        ]);

        $response = $this->postJson("/api/chat/sessions/{$sessionLocked->id}/messages", [
            'content' => 'Hello test message',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Emergency lock: messaging disabled on this line.',
        ]);
    }

    public function test_message_sending_allowed_for_session_with_unlocked_number(): void
    {
        SystemSetting::query()->create([
            'live_chat_locked' => false,
            'live_chat_locked_message' => 'Emergency lock active.',
            'live_chat_locked_phone_numbers' => [
                [
                    'id' => '1029384756',
                    'phone_number_id' => '1029384756',
                    'display_phone_number' => '+27820000001',
                    'label' => '+27820000001 (Locked Line)',
                ],
            ],
            'disable_chat_for_opted_out_clients' => false,
        ]);

        $bank = Bank::query()->create([
            'name' => 'Bank B',
            'code' => 'bank-b',
            'status' => 'Active',
            'primary_whatsapp_number' => '+27820000002',
        ]);

        $user = $this->createAdminUser($bank);
        Sanctum::actingAs($user);

        $client = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Active Client B',
            'phone' => '+27764445566',
            'opt_in' => 'yes',
        ]);

        // Session associated with a different (unlocked) WABA phone number ID
        $sessionUnlocked = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'waba_phone_number_id' => '999888777',
            'status' => 'active',
            'platform' => 'internal',
        ]);

        $response = $this->postJson("/api/chat/sessions/{$sessionUnlocked->id}/messages", [
            'content' => 'Hello unlocked message',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $sessionUnlocked->id,
            'content' => 'Hello unlocked message',
        ]);
    }

    public function test_template_sending_blocked_for_session_matching_locked_number(): void
    {
        SystemSetting::query()->create([
            'live_chat_locked' => false,
            'live_chat_locked_message' => 'Templates locked for this number.',
            'live_chat_locked_phone_numbers' => [
                [
                    'id' => '1029384756',
                    'phone_number_id' => '1029384756',
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

        $user = $this->createAdminUser($bank);
        Sanctum::actingAs($user);

        $client = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Active Client',
            'phone' => '+27761112233',
            'opt_in' => 'yes',
        ]);

        $sessionLocked = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'waba_phone_number_id' => '1029384756',
            'status' => 'active',
            'platform' => 'whatsapp',
        ]);

        $response = $this->postJson("/api/chat/sessions/{$sessionLocked->id}/templates", [
            'template_id' => 'HX1234567890',
            'variables' => [],
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Templates locked for this number.',
        ]);
    }

    public function test_global_emergency_lock_overrides_and_locks_all_sessions(): void
    {
        SystemSetting::query()->create([
            'live_chat_locked' => true,
            'live_chat_locked_message' => 'All live chat communication is locked globally.',
            'live_chat_locked_phone_numbers' => [],
            'disable_chat_for_opted_out_clients' => false,
        ]);

        $bank = Bank::query()->create([
            'name' => 'Bank A',
            'code' => 'bank-a',
            'status' => 'Active',
        ]);

        $user = $this->createAdminUser($bank);
        Sanctum::actingAs($user);

        $client = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Client Test',
            'phone' => '+27761112233',
            'opt_in' => 'yes',
        ]);

        $session = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'status' => 'active',
            'platform' => 'internal',
        ]);

        $response = $this->postJson("/api/chat/sessions/{$session->id}/messages", [
            'content' => 'Should be rejected',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'All live chat communication is locked globally.',
        ]);
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
