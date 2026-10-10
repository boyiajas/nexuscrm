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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class WhatsAppTemplateBankAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Bank $bankA;
    protected Bank $bankB;
    protected User $superAdmin;
    protected User $userBankA;
    protected User $userBankB;
    protected WhatsappTemplateCache $templateA;
    protected WhatsappTemplateCache $templateB;
    protected WhatsappTemplateCache $templateAB;
    protected WhatsappTemplateCache $templateUnassigned;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->bankA = Bank::create(['name' => 'First National Bank', 'code' => 'fnb', 'status' => 'Active']);
        $this->bankB = Bank::create(['name' => 'Capfin Bank', 'code' => 'capfin', 'status' => 'Active']);

        // Roles & Permissions
        $adminRole = Role::firstOrCreate(
            ['code' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Super Administrator', 'is_system' => true, 'is_active' => true]
        );

        $managerRole = Role::firstOrCreate(
            ['code' => User::ROLE_MANAGER],
            ['name' => 'Manager', 'is_system' => false, 'is_active' => true]
        );

        $permSettings = Permission::firstOrCreate(['code' => 'settings_system'], ['name' => 'System Settings', 'module' => 'Settings']);
        $permWabaTemplates = Permission::firstOrCreate(['code' => 'settings_waba_templates'], ['name' => 'WABA Templates', 'module' => 'Settings']);
        $permChat = Permission::firstOrCreate(['code' => 'chat_manage'], ['name' => 'Manage Chat', 'module' => 'Chat']);

        $managerRole->permissions()->syncWithoutDetaching([$permSettings->id, $permWabaTemplates->id, $permChat->id]);
        $adminRole->permissions()->syncWithoutDetaching([$permSettings->id, $permWabaTemplates->id, $permChat->id]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
            'is_super_admin' => true,
        ]);
        $this->superAdmin->roles()->attach($adminRole->id);

        $this->userBankA = User::create([
            'name' => 'Bank A User',
            'email' => 'user_bank_a_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_MANAGER,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
            'is_super_admin' => false,
            'bank_id' => $this->bankA->id,
        ]);
        $this->userBankA->roles()->attach($managerRole->id);
        $this->userBankA->banks()->sync([$this->bankA->id]);

        $this->userBankB = User::create([
            'name' => 'Bank B User',
            'email' => 'user_bank_b_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_MANAGER,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
            'is_super_admin' => false,
            'bank_id' => $this->bankB->id,
        ]);
        $this->userBankB->roles()->attach($managerRole->id);
        $this->userBankB->banks()->sync([$this->bankB->id]);

        // Templates
        $this->templateA = WhatsappTemplateCache::create([
            'sid' => 'template_bank_a',
            'meta_id' => '10000000001',
            'friendly_name' => 'FNB Payment Notice',
            'language' => 'en_US',
            'category' => 'utility',
            'status' => 'approved',
            'body_preview' => 'Dear customer, your FNB payment is due.',
            'variables' => [],
            'synced_at' => now(),
        ]);
        $this->templateA->banks()->sync([$this->bankA->id]);

        $this->templateB = WhatsappTemplateCache::create([
            'sid' => 'template_bank_b',
            'meta_id' => '10000000002',
            'friendly_name' => 'Capfin Balance Alert',
            'language' => 'en_US',
            'category' => 'utility',
            'status' => 'approved',
            'body_preview' => 'Dear customer, your Capfin balance is updated.',
            'variables' => [],
            'synced_at' => now(),
        ]);
        $this->templateB->banks()->sync([$this->bankB->id]);

        $this->templateAB = WhatsappTemplateCache::create([
            'sid' => 'template_shared_ab',
            'meta_id' => '10000000003',
            'friendly_name' => 'Joint Partner Promo',
            'language' => 'en_US',
            'category' => 'marketing',
            'status' => 'approved',
            'body_preview' => 'Special promotion for FNB and Capfin customers.',
            'variables' => [],
            'synced_at' => now(),
        ]);
        $this->templateAB->banks()->sync([$this->bankA->id, $this->bankB->id]);

        $this->templateUnassigned = WhatsappTemplateCache::create([
            'sid' => 'template_unassigned',
            'meta_id' => '10000000004',
            'friendly_name' => 'Orphaned System Template',
            'language' => 'en_US',
            'category' => 'utility',
            'status' => 'approved',
            'body_preview' => 'System maintenance notification.',
            'variables' => [],
            'synced_at' => now(),
        ]);
    }

    public function test_super_admin_can_view_all_templates_across_all_banks(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/whatsapp-templates');

        $response->assertOk();
        $data = $response->json();

        $sids = array_column($data, 'sid');
        $this->assertContains('template_bank_a', $sids);
        $this->assertContains('template_bank_b', $sids);
        $this->assertContains('template_shared_ab', $sids);
        $this->assertContains('template_unassigned', $sids);

        // Verify that bank metadata is returned in toApiArray
        $tA = collect($data)->firstWhere('sid', 'template_bank_a');
        $this->assertNotEmpty($tA['banks']);
        $this->assertEquals($this->bankA->id, $tA['banks'][0]['id']);
        $this->assertEquals([$this->bankA->id], $tA['bank_ids']);

        $tAB = collect($data)->firstWhere('sid', 'template_shared_ab');
        $this->assertCount(2, $tAB['banks']);
        $this->assertEqualsCanonicalizing([$this->bankA->id, $this->bankB->id], $tAB['bank_ids']);
    }

    public function test_scoped_user_can_only_view_templates_of_their_assigned_bank(): void
    {
        Sanctum::actingAs($this->userBankA);

        $response = $this->getJson('/api/whatsapp-templates');

        $response->assertOk();
        $data = $response->json();
        $sids = array_column($data, 'sid');

        // Can see Bank A and Shared AB
        $this->assertContains('template_bank_a', $sids);
        $this->assertContains('template_shared_ab', $sids);

        // CANNOT see Bank B or Unassigned
        $this->assertNotContains('template_bank_b', $sids);
        $this->assertNotContains('template_unassigned', $sids);
    }

    public function test_scoped_user_bank_filter_is_strictly_enforced(): void
    {
        Sanctum::actingAs($this->userBankA);

        // Filter by Bank A returns Bank A & Shared AB
        $responseA = $this->getJson('/api/whatsapp-templates?bank_id=' . $this->bankA->id);
        $responseA->assertOk();
        $sidsA = array_column($responseA->json(), 'sid');
        $this->assertContains('template_bank_a', $sidsA);
        $this->assertContains('template_shared_ab', $sidsA);
        $this->assertNotContains('template_bank_b', $sidsA);

        // Filter by Bank B (which user has NO access to) returns empty array
        $responseB = $this->getJson('/api/whatsapp-templates?bank_id=' . $this->bankB->id);
        $responseB->assertOk();
        $this->assertEmpty($responseB->json());

        // Filter by unassigned returns empty array for non-super-admin
        $responseUnassigned = $this->getJson('/api/whatsapp-templates?bank_id=unassigned');
        $responseUnassigned->assertOk();
        $this->assertEmpty($responseUnassigned->json());
    }

    public function test_super_admin_can_filter_by_bank_and_unassigned(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Filter by Bank B
        $responseB = $this->getJson('/api/whatsapp-templates?bank_id=' . $this->bankB->id);
        $responseB->assertOk();
        $sidsB = array_column($responseB->json(), 'sid');
        $this->assertContains('template_bank_b', $sidsB);
        $this->assertContains('template_shared_ab', $sidsB);
        $this->assertNotContains('template_bank_a', $sidsB);

        // Filter by unassigned
        $responseUnassigned = $this->getJson('/api/whatsapp-templates?bank_id=unassigned');
        $responseUnassigned->assertOk();
        $sidsUnassigned = array_column($responseUnassigned->json(), 'sid');
        $this->assertContains('template_unassigned', $sidsUnassigned);
        $this->assertNotContains('template_bank_a', $sidsUnassigned);
        $this->assertNotContains('template_bank_b', $sidsUnassigned);
    }

    public function test_scoped_user_cannot_view_details_of_other_bank_template(): void
    {
        Sanctum::actingAs($this->userBankA);

        // Attempting to show Template B should return 403 Forbidden
        $responseForbidden = $this->getJson('/api/whatsapp-templates/' . $this->templateB->sid);
        $responseForbidden->assertStatus(403);

        // Showing Template A should succeed
        $responseOk = $this->getJson('/api/whatsapp-templates/' . $this->templateA->sid);
        $responseOk->assertOk();
        $responseOk->assertJsonPath('template.sid', 'template_bank_a');
    }

    public function test_template_creation_assigns_multiple_banks(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $mock = Mockery::mock(WhatsAppServiceInterface::class);
        $mock->shouldReceive('createWhatsAppTemplate')
            ->once()
            ->andReturn([
                'sid' => 'multi_bank_test_template',
                'meta_id' => '99999000001',
                'friendly_name' => 'multi_bank_test_template',
                'language' => 'en_US',
                'preview' => 'Hello {{1}}, welcome to our multi-bank service.',
                'variables' => ['1'],
                'whatsapp' => [
                    'status' => 'PENDING',
                    'category' => 'utility',
                ],
            ]);
        $this->app->instance(WhatsAppServiceInterface::class, $mock);

        $payload = [
            'friendly_name' => 'Multi Bank Test Template',
            'body' => 'Hello {{1}}, welcome to our multi-bank service.',
            'language' => 'en_US',
            'category' => 'UTILITY',
            'bank_ids' => [$this->bankA->id, $this->bankB->id],
            'body_examples' => ['John'],
        ];

        $response = $this->postJson('/api/whatsapp-templates', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('meta_id', '99999000001');

        $createdSid = $response->json('sid');
        $created = WhatsappTemplateCache::with('banks')->where('sid', $createdSid)->firstOrFail();

        $this->assertCount(2, $created->banks);
        $this->assertEqualsCanonicalizing([$this->bankA->id, $this->bankB->id], $created->banks->pluck('id')->all());
    }

    public function test_scoped_user_cannot_create_template_for_unauthorized_bank(): void
    {
        Sanctum::actingAs($this->userBankA);

        $payload = [
            'friendly_name' => 'Unauthorized Bank Template',
            'body' => 'Hello {{1}}, test body.',
            'language' => 'en_US',
            'category' => 'UTILITY',
            'bank_ids' => [$this->bankB->id], // Bank A user trying to assign Bank B
            'body_examples' => ['John'],
        ];

        $response = $this->postJson('/api/whatsapp-templates', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['bank_ids']);
    }

    public function test_template_update_syncs_assigned_banks_without_meta_call_when_only_banks_change(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // No Meta API call expected because only banks are changing
        $mock = Mockery::mock(WhatsAppServiceInterface::class);
        $mock->shouldNotReceive('updateWhatsAppTemplate');
        $this->app->instance(WhatsAppServiceInterface::class, $mock);

        $payload = [
            'bank_ids' => [$this->bankB->id],
        ];

        $response = $this->putJson('/api/whatsapp-templates/' . $this->templateA->sid, $payload);

        $response->assertOk();
        $this->templateA->refresh();
        $this->assertEquals([$this->bankB->id], $this->templateA->banks->pluck('id')->all());
    }

    public function test_scoped_user_cannot_update_template_of_another_bank(): void
    {
        Sanctum::actingAs($this->userBankA);

        $payload = [
            'friendly_name' => 'Hacked Name',
        ];

        $response = $this->putJson('/api/whatsapp-templates/' . $this->templateB->sid, $payload);
        $response->assertStatus(403);
    }

    public function test_scoped_user_cannot_delete_template_of_another_bank(): void
    {
        Sanctum::actingAs($this->userBankA);

        $response = $this->deleteJson('/api/whatsapp-templates/' . $this->templateB->sid);
        $response->assertStatus(403);
    }

    public function test_scoped_user_cannot_send_chat_template_of_another_bank(): void
    {
        Sanctum::actingAs($this->userBankA);

        SystemSetting::query()->create([
            'live_chat_locked' => false,
            'disable_chat_for_opted_out_clients' => false,
            'meta_whatsapp_phone_number_id' => '1247262038476724',
            'meta_environment' => 'development',
        ]);

        $client = Client::create([
            'bank_id' => $this->bankA->id,
            'name' => 'John Doe',
            'phone' => '+27821112233',
            'opt_in' => 'yes',
        ]);

        $session = ChatSession::create([
            'bank_id' => $this->bankA->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'phone' => $client->phone,
            'status' => 'active',
            'platform' => 'whatsapp',
        ]);

        $mock = $this->mock(WhatsAppServiceInterface::class);
        $mock->shouldReceive('sendTemplateFromSubjectMessage')
            ->once()
            ->andReturn(['message_id' => 'wamid.test.123']);

        // Attempting to send Template B (belonging to Bank B) should be rejected
        $responseB = $this->postJson("/api/chat/sessions/{$session->id}/templates", [
            'template_id' => $this->templateB->sid,
            'variables' => [],
        ]);

        $responseB->assertStatus(403);
        $responseB->assertJson(['message' => 'You do not have access to this WhatsApp template.']);

        // Sending Template A (belonging to Bank A) should proceed to WhatsApp service
        $responseA = $this->postJson("/api/chat/sessions/{$session->id}/templates", [
            'template_id' => $this->templateA->sid,
            'variables' => [],
        ]);

        $responseA->assertStatus(201);
        $responseA->assertJsonPath('is_template', true);
        $responseA->assertJsonPath('delivery_status', 'accepted');
    }

    public function test_export_includes_bank_column_and_scopes_for_non_admin(): void
    {
        Sanctum::actingAs($this->userBankA);

        $response = $this->get('/api/whatsapp-templates/export');

        $response->assertOk();
        $content = $response->streamedContent();

        // Bank header is present
        $this->assertStringContainsString('Bank(s)', $content);

        // Bank A template and Shared AB template are in export
        $this->assertStringContainsString('FNB Payment Notice', $content);
        $this->assertStringContainsString('Joint Partner Promo', $content);

        // Bank B template is NOT in export
        $this->assertStringNotContainsString('Capfin Balance Alert', $content);
    }
}
