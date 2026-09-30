<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppServiceInterface;
use App\Models\Bank;
use App\Models\ChatSession;
use App\Models\Client;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsappTemplateCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatOptOutLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_filters_endpoint_returns_opt_out_chat_lock_settings(): void
    {
        SystemSetting::query()->create([
            'disable_chat_for_opted_out_clients' => true,
            'opted_out_chat_message' => 'Client is opted out.',
        ]);

        $bank = Bank::query()->create([
            'name' => 'Test Bank',
            'code' => 'test-bank',
            'status' => 'Active',
        ]);

        $user = $this->createChatManager($bank);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/chat/filters');

        $response->assertOk();
        $response->assertJson([
            'disableChatForOptedOutClients' => true,
            'optedOutChatMessage' => 'Client is opted out.',
        ]);
    }

    public function test_opted_out_client_cannot_be_sent_message_when_lock_enabled(): void
    {
        SystemSetting::query()->create([
            'disable_chat_for_opted_out_clients' => true,
            'opted_out_chat_message' => 'Messaging is disabled because client opted out.',
        ]);

        $bank = Bank::query()->create([
            'name' => 'Test Bank',
            'code' => 'test-bank',
            'status' => 'Active',
        ]);

        $user = $this->createChatManager($bank);
        Sanctum::actingAs($user);

        $client = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Opted Out Client',
            'phone' => '+27761234567',
            'opt_in' => 'no',
            'whatsapp_opted_out_at' => now(),
        ]);

        $session = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'status' => 'active',
            'platform' => 'whatsapp',
        ]);

        $response = $this->postJson("/api/chat/sessions/{$session->id}/messages", [
            'content' => 'Hello there',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Messaging is disabled because client opted out.',
        ]);
    }

    public function test_opted_out_client_cannot_be_sent_template_when_lock_enabled(): void
    {
        SystemSetting::query()->create([
            'disable_chat_for_opted_out_clients' => true,
            'opted_out_chat_message' => 'Opted out clients cannot receive templates.',
        ]);

        $bank = Bank::query()->create([
            'name' => 'Test Bank',
            'code' => 'test-bank',
            'status' => 'Active',
        ]);

        $user = $this->createChatManager($bank);
        Sanctum::actingAs($user);

        $client = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Opted Out Client',
            'phone' => '+27761234567',
            'opt_in' => 'no',
            'whatsapp_opted_out_at' => now(),
        ]);

        $session = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'status' => 'active',
            'platform' => 'whatsapp',
        ]);

        $response = $this->postJson("/api/chat/sessions/{$session->id}/templates", [
            'template_id' => 'HX1234567890',
            'variables' => [],
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Opted out clients cannot receive templates.',
        ]);
    }

    public function test_opted_out_client_can_be_messaged_when_lock_is_disabled(): void
    {
        SystemSetting::query()->create([
            'disable_chat_for_opted_out_clients' => false,
            'whatsapp_provider' => 'meta',
            'meta_environment' => 'development',
        ]);

        $bank = Bank::query()->create([
            'name' => 'Test Bank',
            'code' => 'test-bank',
            'status' => 'Active',
        ]);

        $user = $this->createChatManager($bank);
        Sanctum::actingAs($user);

        $client = Client::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Opted Out Client',
            'phone' => '+27761234567',
            'opt_in' => 'no',
            'whatsapp_opted_out_at' => now(),
        ]);

        $session = ChatSession::query()->create([
            'bank_id' => $bank->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'status' => 'active',
            'platform' => 'internal', // use internal platform so external provider call is bypassed
        ]);

        $response = $this->postJson("/api/chat/sessions/{$session->id}/messages", [
            'content' => 'Hello there',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'content' => 'Hello there',
        ]);
    }

    public function test_settings_api_can_update_opt_out_chat_lock_settings(): void
    {
        $bank = Bank::query()->create([
            'name' => 'Test Bank',
            'code' => 'test-bank',
            'status' => 'Active',
        ]);

        $user = $this->createChatManager($bank);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/settings', [
            'disable_chat_for_opted_out_clients' => false,
            'opted_out_chat_message' => 'Custom disabled message.',
        ]);

        $response->assertOk();
        $response->assertJson([
            'disable_chat_for_opted_out_clients' => false,
            'opted_out_chat_message' => 'Custom disabled message.',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'disable_chat_for_opted_out_clients' => false,
            'opted_out_chat_message' => 'Custom disabled message.',
        ]);
    }

    private function createChatManager(Bank $bank): User
    {
        $role = Role::query()->where('code', User::ROLE_SUPER_ADMIN)->firstOrFail();

        $user = User::query()->create([
            'name' => 'Chat Manager',
            'email' => 'chat-manager-' . Str::uuid() . '@example.test',
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

    private function permissionIds(array $permissionCodes): array
    {
        $ids = Permission::query()
            ->whereIn('code', $permissionCodes)
            ->pluck('id')
            ->all();

        return $ids;
    }
}
