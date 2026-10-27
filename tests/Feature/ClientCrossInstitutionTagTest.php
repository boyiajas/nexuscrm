<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Client;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\ClientInstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCrossInstitutionTagTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Bank $bankA;
    protected Bank $bankB;
    protected Bank $bankC;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bankA = Bank::create(['name' => 'FNB Pre Legal', 'code' => 'fnb-pre-legal', 'status' => 'Active']);
        $this->bankB = Bank::create(['name' => 'Capfin Loans', 'code' => 'capfin-loans', 'status' => 'Active']);
        $this->bankC = Bank::create(['name' => 'FinChoice', 'code' => 'finchoice', 'status' => 'Active']);

        $role = Role::query()->where('code', User::ROLE_SUPER_ADMIN)->first()
            ?? Role::create(['name' => 'Super Administrator', 'code' => User::ROLE_SUPER_ADMIN]);

        $this->superAdmin = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin-' . \Illuminate\Support\Str::uuid() . '@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('Password123!'),
            'password_changed_at' => now(),
            'password_reset_required' => false,
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'bank_id' => $this->bankA->id,
        ]);

        $this->superAdmin->roles()->sync([$role->id]);
        $this->superAdmin->banks()->sync([$this->bankA->id, $this->bankB->id, $this->bankC->id]);

        $this->department = Department::create(['name' => 'Recoveries', 'code' => 'REC']);
    }

    public function test_clients_with_matching_id_numbers_across_banks_are_tagged_with_associated_bank_names(): void
    {
        $clientA = Client::create([
            'name' => 'John Doe',
            'id_number' => '8501015000085',
            'phone' => '0821112233',
            'bank_id' => $this->bankA->id,
            'bank_name' => $this->bankA->name,
            'account_number' => 'ACC100',
        ]);

        $clientB = Client::create([
            'name' => 'John Doe',
            'id_number' => '8501015000085',
            'phone' => '0829998877', // different phone, but same ID number
            'bank_id' => $this->bankB->id,
            'bank_name' => $this->bankB->name,
            'account_number' => 'ACC200',
        ]);

        $service = app(ClientInstitutionService::class);
        $service->syncClientInstitutionTags($clientA, syncPeers: true);

        $clientA->refresh();
        $clientB->refresh();

        $this->assertContains('Capfin Loans', $clientA->tags);
        $this->assertNotContains('FNB Pre Legal', $clientA->tags);

        $this->assertContains('FNB Pre Legal', $clientB->tags);
        $this->assertNotContains('Capfin Loans', $clientB->tags);
    }

    public function test_clients_without_id_number_fallback_to_matching_by_phone_or_email(): void
    {
        $clientA = Client::create([
            'name' => 'Jane Smith',
            'id_number' => null,
            'phone' => '0842575612',
            'email' => 'jane@example.com',
            'bank_id' => $this->bankA->id,
            'bank_name' => $this->bankA->name,
            'account_number' => 'ACC300',
        ]);

        $clientB = Client::create([
            'name' => 'Jane Smith',
            'id_number' => '', // blank
            'phone' => '0842575612', // same phone
            'bank_id' => $this->bankB->id,
            'bank_name' => $this->bankB->name,
            'account_number' => 'ACC400',
        ]);

        $service = app(ClientInstitutionService::class);
        $service->syncClientInstitutionTags($clientA, syncPeers: true);

        $clientA->refresh();
        $clientB->refresh();

        $this->assertContains('Capfin Loans', $clientA->tags);
        $this->assertContains('FNB Pre Legal', $clientB->tags);
    }

    public function test_clients_with_different_non_empty_id_numbers_do_not_match_even_if_phone_matches(): void
    {
        $clientA = Client::create([
            'name' => 'Person One',
            'id_number' => '8001010000001',
            'phone' => '0831234567',
            'bank_id' => $this->bankA->id,
            'bank_name' => $this->bankA->name,
        ]);

        $clientB = Client::create([
            'name' => 'Person Two',
            'id_number' => '9002020000002', // different ID number
            'phone' => '0831234567', // same shared household phone
            'bank_id' => $this->bankB->id,
            'bank_name' => $this->bankB->name,
        ]);

        $service = app(ClientInstitutionService::class);
        $service->syncClientInstitutionTags($clientA, syncPeers: true);

        $clientA->refresh();
        $clientB->refresh();

        $this->assertEmpty($clientA->tags ?? []);
        $this->assertEmpty($clientB->tags ?? []);
    }

    public function test_client_api_index_and_show_return_linked_institutions(): void
    {
        $clientA = Client::create([
            'name' => 'Multi Client',
            'id_number' => '7503030000003',
            'bank_id' => $this->bankA->id,
            'bank_name' => $this->bankA->name,
        ]);

        $clientB = Client::create([
            'name' => 'Multi Client',
            'id_number' => '7503030000003',
            'bank_id' => $this->bankC->id,
            'bank_name' => $this->bankC->name,
        ]);

        $responseShow = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/clients/{$clientA->id}");

        $responseShow->assertOk();
        $responseShow->assertJsonFragment([
            'linked_institutions' => ['FinChoice'],
        ]);

        $responseIndex = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/clients?per_page=25");

        $responseIndex->assertOk();
        $items = collect($responseIndex->json('data'));
        $itemA = $items->firstWhere('id', $clientA->id);
        $this->assertNotNull($itemA);
        $this->assertEquals(['FinChoice'], $itemA['linked_institutions']);
    }

    public function test_creating_and_updating_client_syncs_institution_tags(): void
    {
        $existing = Client::create([
            'name' => 'Existing Capfin Client',
            'id_number' => '8808080000008',
            'bank_id' => $this->bankB->id,
            'bank_name' => $this->bankB->name,
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/clients', [
                'name' => 'New FNB Client',
                'id_number' => '8808080000008',
                'bank_id' => $this->bankA->id,
                'department_ids' => [$this->department->id],
            ]);

        $response->assertCreated();
        $newClientId = $response->json('id');

        $newClient = Client::find($newClientId);
        $existing->refresh();

        $this->assertContains('Capfin Loans', $newClient->tags);
        $this->assertContains('FNB Pre Legal', $existing->tags);
    }

    public function test_artisan_sync_command_synchronizes_all_clients(): void
    {
        $clientA = Client::create([
            'name' => 'Sync Test A',
            'id_number' => '9901010000009',
            'bank_id' => $this->bankA->id,
            'bank_name' => $this->bankA->name,
            'tags' => ['CustomTag'],
        ]);

        $clientB = Client::create([
            'name' => 'Sync Test B',
            'id_number' => '9901010000009',
            'bank_id' => $this->bankB->id,
            'bank_name' => $this->bankB->name,
            'tags' => [],
        ]);

        $this->artisan('clients:sync-institution-tags')
            ->expectsOutputToContain('Successfully processed and synchronized tags')
            ->assertSuccessful();

        $clientA->refresh();
        $clientB->refresh();

        $this->assertContains('Capfin Loans', $clientA->tags);
        $this->assertContains('CustomTag', $clientA->tags);
        $this->assertContains('FNB Pre Legal', $clientB->tags);
    }
}
