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
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatClientLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_for_client_links_unknown_inbound_chat_session(): void
    {
        $bank = Bank::query()->create([
            'name' => 'Finchoice',
            'code' => 'finchoice',
            'status' => 'Active',
        ]);

        $department = \App\Models\Department::query()->create([
            'name' => 'Support',
            'code' => 'support',
            'status' => 'Active',
            'bank_id' => $bank->id,
        ]);

        $user = $this->createChatManager($bank, $department);
        $client = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Inbound Client',
            'phone' => '+27763399083',
            'bank_name' => $bank->name,
        ]);
        $client->departments()->sync([$department->id]);

        $session = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => null,
            'client_name' => '+27763399083',
            'phone' => '+27763399083',
            'status' => 'active',
            'platform' => 'whatsapp',
            'waba_phone_number_id' => 'waba-123',
            'last_message' => 'good morning',
            'unread_count' => 1,
        ]);

        ChatMessage::query()->create([
            'chat_session_id' => $session->id,
            'sender' => 'user',
            'content' => 'good morning',
            'sent_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/chat/session-for-client', [
            'client_id' => $client->id,
            'platform' => 'whatsapp',
            'waba_number' => 'waba-123',
            'source_chat_session_id' => $session->id,
        ])
            ->assertOk()
            ->assertJsonPath('id', $session->id)
            ->assertJsonPath('client_id', $client->id)
            ->assertJsonCount(1, 'messages');

        $this->assertDatabaseHas('chat_sessions', [
            'id' => $session->id,
            'client_id' => $client->id,
            'client_name' => 'Inbound Client',
            'phone' => '+27763399083',
            'bank_id' => $bank->id,
            'waba_phone_number_id' => 'waba-123',
            'unread_count' => 0,
        ]);
    }

    public function test_sending_message_longer_than_255_characters_updates_last_message_without_truncation_error(): void
    {
        $bank = Bank::query()->create([
            'name' => 'Finchoice',
            'code' => 'finchoice',
            'status' => 'Active',
        ]);

        $department = \App\Models\Department::query()->create([
            'name' => 'Support',
            'code' => 'support',
            'status' => 'Active',
            'bank_id' => $bank->id,
        ]);

        $user = $this->createChatManager($bank, $department);

        $client = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Miss Mthonti',
            'phone' => '+27763399083',
            'bank_name' => $bank->name,
        ]);
        $client->departments()->sync([$department->id]);

        $session = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => 'Miss Mthonti',
            'phone' => '+27763399083',
            'status' => 'active',
            'platform' => 'whatsapp',
            'waba_phone_number_id' => 'waba-123',
            'last_message' => 'Initial',
            'unread_count' => 0,
        ]);

        \App\Models\SystemSetting::query()->create([
            'meta_environment' => 'sandbox',
        ]);

        Sanctum::actingAs($user);

        $longMessage = 'Good morning Miss Mthonti. Please note that your Finchoice account was closed with our office on the 27 July 2026. Kindly check the date on which you received that email as the 50% discount was only available in June 2026. Kindly contact Finchoice directly on 0861346246 for further assistance.';

        $this->assertGreaterThan(255, strlen($longMessage));

        $this->postJson("/api/chat/sessions/{$session->id}/messages", [
            'content' => $longMessage,
        ])->assertCreated();

        $this->assertDatabaseHas('chat_sessions', [
            'id' => $session->id,
            'last_message' => $longMessage,
        ]);
    }

    public function test_live_chat_shows_read_only_after_a_matching_meta_read_receipt(): void
    {
        $session = $this->createWhatsAppSessionForDeliveryTest();
        $providerId = 'wamid.live-chat-read-1';
        $this->mock(WhatsAppServiceInterface::class)
            ->shouldReceive('sendPlainWhatsapp')
            ->once()
            ->andReturn(['message_id' => $providerId, 'status' => 'accepted']);

        $response = $this->postJson("/api/chat/sessions/{$session->id}/messages", ['content' => 'Hello customer'])
            ->assertCreated()
            ->assertJsonPath('provider_message_id', $providerId)
            ->assertJsonPath('delivery_status', 'accepted');

        $messageId = $response->json('id');

        $this->postMetaStatus($providerId, 'sent', '1790000000')->assertOk();
        $this->assertDatabaseHas('chat_messages', ['id' => $messageId, 'delivery_status' => 'sent']);

        $this->postMetaStatus($providerId, 'delivered', '1790000001')->assertOk();
        $this->assertDatabaseHas('chat_messages', ['id' => $messageId, 'delivery_status' => 'delivered']);

        $this->postMetaStatus($providerId, 'read', '1790000002')->assertOk();
        $this->getJson("/api/chat/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('messages.0.delivery_status', 'read');

        // Meta can deliver older notifications after the read receipt.
        $this->postMetaStatus($providerId, 'delivered', '1790000003')->assertOk();
        $this->assertDatabaseHas('chat_messages', ['id' => $messageId, 'delivery_status' => 'read']);
    }

    public function test_failed_live_chat_send_does_not_appear_delivered_or_read(): void
    {
        $session = $this->createWhatsAppSessionForDeliveryTest();
        $this->mock(WhatsAppServiceInterface::class)
            ->shouldReceive('sendPlainWhatsapp')
            ->once()
            ->andThrow(new \RuntimeException('Meta rejected this message'));

        $this->postJson("/api/chat/sessions/{$session->id}/messages", ['content' => 'Hello customer'])
            ->assertCreated()
            ->assertJsonPath('delivery_status', 'failed')
            ->assertJsonPath('provider_message_id', null);

        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'delivery_status' => 'failed',
        ]);
    }

    public function test_timed_out_live_chat_send_is_marked_unknown_instead_of_failed(): void
    {
        $session = $this->createWhatsAppSessionForDeliveryTest();
        $this->mock(WhatsAppServiceInterface::class)
            ->shouldReceive('sendPlainWhatsapp')
            ->once()
            ->andThrow(new \Illuminate\Http\Client\ConnectionException('Meta timed out'));

        $this->postJson("/api/chat/sessions/{$session->id}/messages", ['content' => 'Hello customer'])
            ->assertCreated()
            ->assertJsonPath('delivery_status', 'unknown')
            ->assertJsonPath('provider_message_id', null);
    }

    public function test_unmatched_live_chat_receipt_does_not_change_a_campaign_for_the_same_phone(): void
    {
        $bank = Bank::query()->create(['name' => 'Receipt Bank', 'code' => 'receipt', 'status' => 'Active']);
        $client = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Shared Customer',
            'phone' => '+27763399083',
            'bank_name' => $bank->name,
        ]);
        $campaign = Campaign::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Earlier campaign',
            'channels' => ['WhatsApp'],
        ]);
        $batch = CampaignWhatsappMessage::query()->create(['campaign_id' => $campaign->id]);
        $recipient = CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $batch->id,
            'client_id' => $client->id,
            'phone' => '+27763399083',
            'status' => 'Sent',
            'provider_message_id' => 'wamid.campaign-message',
        ]);

        $this->mock(WhatsAppServiceInterface::class);

        $this->postMetaStatus('wamid.unmatched-live-chat', 'read', '1790000004')->assertOk();

        $this->assertDatabaseHas('campaign_whatsapp_recipients', [
            'id' => $recipient->id,
            'provider_message_id' => 'wamid.campaign-message',
            'status' => 'Sent',
        ]);
    }

    private function createWhatsAppSessionForDeliveryTest(): ChatSession
    {
        $bank = Bank::query()->create(['name' => 'Receipt Bank', 'code' => 'receipt', 'status' => 'Active']);
        $department = \App\Models\Department::query()->create([
            'name' => 'Support', 'code' => 'receipt-support', 'status' => 'Active', 'bank_id' => $bank->id,
        ]);
        Sanctum::actingAs($this->createChatManager($bank, $department));
        \App\Models\SystemSetting::query()->create(['meta_environment' => 'sandbox']);

        return ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_name' => 'Receipt Customer',
            'phone' => '+27763399083',
            'status' => 'active',
            'platform' => 'whatsapp',
            'unread_count' => 0,
        ]);
    }

    private function postMetaStatus(string $providerId, string $status, string $timestamp)
    {
        return $this->postJson('/api/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'statuses' => [[
                            'id' => $providerId,
                            'status' => $status,
                            'timestamp' => $timestamp,
                            'recipient_id' => '27763399083',
                        ]],
                    ],
                ]],
            ]],
        ]);
    }

    private function createChatManager(Bank $bank, ?\App\Models\Department $department = null): User
    {
        $role = Role::query()->where('code', User::ROLE_ADMIN)->firstOrFail();
        $role->permissions()->sync($this->permissionIds(['send_whatsapp']));

        $user = User::query()->create([
            'name' => 'Chat Manager',
            'email' => 'chat-manager-' . Str::uuid() . '@example.test',
            'password' => Hash::make('Password123!'),
            'password_changed_at' => now(),
            'password_reset_required' => false,
            'role' => User::ROLE_ADMIN,
            'status' => 'Active',
            'bank_id' => $bank->id,
        ]);

        $user->roles()->sync([$role->id]);
        $user->banks()->sync([$bank->id]);
        if ($department) {
            $user->departments()->sync([$department->id]);
        }

        return $user->fresh(['bank', 'banks', 'roles', 'departments']);
    }

    private function permissionIds(array $permissionCodes): array
    {
        $ids = Permission::query()
            ->whereIn('code', $permissionCodes)
            ->pluck('id')
            ->all();

        $this->assertCount(count($permissionCodes), $ids, 'One or more expected permissions were not seeded.');

        return $ids;
    }
}
