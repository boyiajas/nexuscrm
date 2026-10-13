<?php

namespace Tests\Feature;

use App\Jobs\ImportClientsJob;
use App\Models\Bank;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Department;
use App\Models\ImportUpload;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppFlow;
use App\Services\WhatsAppBatchService;
use App\Traits\HasImportHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientSpecialityFieldTest extends TestCase
{
    use RefreshDatabase;

    protected Bank $bank;
    protected Department $department;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = Bank::query()->create([
            'name' => 'Speciality Test Bank',
            'code' => 'spec-bank',
            'status' => 'Active',
        ]);

        $this->department = Department::query()->create([
            'name' => 'Medical Recoveries',
            'code' => 'med-rec',
            'status' => 'Active',
            'bank_id' => $this->bank->id,
        ]);

        $role = Role::query()->firstOrCreate(
            ['code' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Super Administrator', 'is_system' => true, 'is_active' => true]
        );

        $this->user = User::factory()->create([
            'bank_id' => $this->bank->id,
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);
        $this->user->roles()->sync([$role->id]);

        Sanctum::actingAs($this->user);
    }

    public function test_client_can_be_created_with_optional_speciality(): void
    {
        $response = $this->postJson('/api/clients', [
            'name' => 'Dr. Jane Smith',
            'bank_id' => $this->bank->id,
            'department_ids' => [$this->department->id],
            'speciality' => 'Cardiology',
            'phone' => '+27821112233',
        ]);

        $response->assertCreated()
            ->assertJsonPath('speciality', 'Cardiology');

        $clientId = $response->json('id');
        $this->assertDatabaseHas('clients', [
            'id' => $clientId,
            'name' => 'Dr. Jane Smith',
            'speciality' => 'Cardiology',
        ]);
    }

    public function test_client_can_be_created_without_speciality_field(): void
    {
        // Speciality should NOT be required
        $response = $this->postJson('/api/clients', [
            'name' => 'John Doe Without Speciality',
            'bank_id' => $this->bank->id,
            'department_ids' => [$this->department->id],
            'phone' => '+27824445566',
        ]);

        $response->assertCreated()
            ->assertJsonPath('speciality', null);

        $clientId = $response->json('id');
        $this->assertDatabaseHas('clients', [
            'id' => $clientId,
            'name' => 'John Doe Without Speciality',
            'speciality' => null,
        ]);
    }

    public function test_client_can_be_updated_with_speciality(): void
    {
        $client = Client::create([
            'bank_id' => $this->bank->id,
            'name' => 'Dr. Gregory House',
            'phone' => '+27829998877',
            'speciality' => null,
        ]);
        $client->departments()->sync([$this->department->id]);

        $response = $this->putJson("/api/clients/{$client->id}", [
            'speciality' => 'Diagnostics',
        ]);

        $response->assertOk()
            ->assertJsonPath('speciality', 'Diagnostics');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'speciality' => 'Diagnostics',
        ]);
    }

    public function test_has_import_helpers_recognizes_speciality_and_aliases(): void
    {
        $helper = new class {
            use HasImportHelpers;

            public function getSupported()
            {
                return $this->supportedImportColumns();
            }

            public function getAliases()
            {
                return $this->importColumnAliases();
            }

            public function getLabel(string $col)
            {
                return $this->importFieldLabel($col);
            }

            public function getDiagnostics(array $header)
            {
                return $this->buildImportHeaderDiagnostics($header);
            }
        };

        $this->assertContains('speciality', $helper->getSupported());
        $this->assertSame('speciality', $helper->getAliases()['speciality'] ?? null);
        $this->assertSame('speciality', $helper->getAliases()['specialty'] ?? null);
        $this->assertSame('speciality', $helper->getAliases()['client_speciality'] ?? null);
        $this->assertSame('speciality', $helper->getAliases()['client_specialty'] ?? null);
        $this->assertSame('Speciality', $helper->getLabel('speciality'));

        // Test that Speciality in headers is recognized and marked optional
        $header = ['Name', 'Cell', 'Speciality'];
        $diagnostics = $helper->getDiagnostics($header);
        $this->assertTrue($diagnostics['is_valid']);
        $this->assertEmpty($diagnostics['missing_required_headers']);

        $recNames = array_column($diagnostics['recognized_columns'], 'normalized');
        $this->assertContains('speciality', $recNames);
    }

    public function test_client_import_with_speciality_populates_database(): void
    {
        $csvContent = "Account Number,Name,Cell,Speciality\n" .
                      "ACC901,DR EMMA WATSON,0821234567,Neurology\n" .
                      "ACC902,DR ALAN GRANT,0827654321,Paleontology\n";

        $tempPath = tempnam(sys_get_temp_dir(), 'spec_') . '.csv';
        file_put_contents($tempPath, $csvContent);

        $upload = ImportUpload::create([
            'bank_id' => $this->bank->id,
            'user_id' => $this->user->id,
            'dataset' => 'clients',
            'original_filename' => 'SPECIALITY_TEST.csv',
            'import_batch_number' => 'IMP-TEST-SPEC-1',
            'stored_path' => $tempPath,
            'import_status' => 'uploaded',
        ]);

        $job = new ImportClientsJob(
            $upload->id,
            $this->user->id,
            $this->bank->id,
            [$this->department->id],
            $tempPath,
            'SPECIALITY_TEST.csv',
            'IMP-TEST-SPEC-1',
            ['status' => 'skipped']
        );
        $job->handle();

        $upload->refresh();
        $this->assertSame('imported', $upload->import_status);
        $this->assertSame(2, $upload->import_summary['imported']);

        $client1 = Client::where('account_number', 'ACC901')->first();
        $this->assertNotNull($client1);
        $this->assertSame('Neurology', $client1->speciality);

        $client2 = Client::where('account_number', 'ACC902')->first();
        $this->assertNotNull($client2);
        $this->assertSame('Paleontology', $client2->speciality);

        @unlink($tempPath);
    }

    public function test_client_import_without_speciality_succeeds_without_error(): void
    {
        $csvContent = "Account Number,Name,Cell\n" .
                      "ACC903,NO SPEC CLIENT,0821112222\n";

        $tempPath = tempnam(sys_get_temp_dir(), 'nospec_') . '.csv';
        file_put_contents($tempPath, $csvContent);

        $upload = ImportUpload::create([
            'bank_id' => $this->bank->id,
            'user_id' => $this->user->id,
            'dataset' => 'clients',
            'original_filename' => 'NO_SPEC_TEST.csv',
            'import_batch_number' => 'IMP-TEST-SPEC-2',
            'stored_path' => $tempPath,
            'import_status' => 'uploaded',
        ]);

        $job = new ImportClientsJob(
            $upload->id,
            $this->user->id,
            $this->bank->id,
            [$this->department->id],
            $tempPath,
            'NO_SPEC_TEST.csv',
            'IMP-TEST-SPEC-2',
            ['status' => 'skipped']
        );
        $job->handle();

        $client = Client::where('account_number', 'ACC903')->first();
        $this->assertNotNull($client);
        $this->assertNull($client->speciality);

        @unlink($tempPath);
    }

    public function test_whatsapp_batch_service_resolves_speciality_variable_mapping(): void
    {
        $client = Client::create([
            'bank_id' => $this->bank->id,
            'name' => 'Dr. Robert Langdon',
            'phone' => '+27823334455',
            'speciality' => 'Symbology',
        ]);

        $campaign = Campaign::create([
            'name' => 'Speciality Campaign',
            'bank_id' => $this->bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        $batchService = app(WhatsAppBatchService::class);

        $templateVariables = [
            '1' => ['source' => 'client.name'],
            '2' => ['source' => 'client.speciality'],
        ];

        $resolved = $batchService->resolveTemplateVariableValues($templateVariables, $client, $campaign);

        $this->assertSame('Dr. Robert Langdon', $resolved['1']);
        $this->assertSame('Symbology', $resolved['2']);

        // When speciality is null/empty
        $clientNoSpec = Client::create([
            'bank_id' => $this->bank->id,
            'name' => 'Regular Client',
            'phone' => '+27825556677',
            'speciality' => null,
        ]);

        $resolvedEmpty = $batchService->resolveTemplateVariableValues($templateVariables, $clientNoSpec, $campaign);
        $this->assertSame(' ', $resolvedEmpty['2']); // Default space for WhatsApp empty param
    }

    public function test_whatsapp_flow_accepts_client_speciality_as_allowed_variable_source(): void
    {
        $template = \App\Models\WhatsappTemplateCache::create([
            'sid' => 'test_template_spec',
            'meta_id' => '123456789',
            'friendly_name' => 'Specialist Notice',
            'language' => 'en_US',
            'category' => 'utility',
            'status' => 'approved',
            'variables' => ['1' => 'Speciality Name'],
            'synced_at' => now(),
        ]);
        $template->banks()->sync([$this->bank->id]);

        $flowData = [
            'name' => 'Speciality Assessment Flow',
            'template_sid' => $template->sid,
            'template_name' => $template->friendly_name,
            'template_language' => 'en_US',
            'status' => 'active',
            'template_variables' => [
                '1' => ['source' => 'client.speciality'],
            ],
            'flow_definition' => [
                [
                    'id' => 'step_1',
                    'reply_type' => 'message',
                    'message' => 'Welcome doctor, please confirm your credentials.',
                ],
            ],
        ];

        $response = $this->postJson('/api/whatsapp-flows', $flowData);

        $response->assertCreated();
        $this->assertDatabaseHas('whatsapp_flows', [
            'name' => 'Speciality Assessment Flow',
        ]);

        $flow = \App\Models\WhatsAppFlow::where('name', 'Speciality Assessment Flow')->first();
        $this->assertSame('client.speciality', $flow->template_variables['1']['source']);
    }
}
