<?php

namespace Tests\Feature;

use App\Jobs\ImportClientsJob;
use App\Models\Bank;
use App\Models\Department;
use App\Models\ImportUpload;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientImportBankSelectionTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bankA;
    private Bank $bankB;
    private Bank $bankC;
    private Department $department;
    private User $superAdmin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();

        $this->bankA = Bank::firstOrCreate(['code' => 'capfin'], ['name' => 'Capfin Loans']);
        $this->bankB = Bank::firstOrCreate(['code' => 'tenacity'], ['name' => 'Tenacity']);
        $this->bankC = Bank::firstOrCreate(['code' => 'african-bank'], ['name' => 'African Bank']);

        $this->department = Department::firstOrCreate(['code' => 'coll'], ['name' => 'Collections']);
        $this->bankA->departments()->attach($this->department->id);
        $this->bankB->departments()->attach($this->department->id);
        $this->bankC->departments()->attach($this->department->id);

        $superAdminRole = Role::firstOrCreate(['code' => User::ROLE_SUPER_ADMIN], ['name' => 'Super Admin']);
        $this->superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);
        $this->superAdmin->roles()->sync([$superAdminRole->id]);

        $importPermission = Permission::firstOrCreate(['code' => 'import_clients'], ['name' => 'Import Clients', 'module' => 'Clients']);
        $agentRole = Role::firstOrCreate(['code' => 'AGENT'], ['name' => 'Agent']);
        $agentRole->permissions()->sync([$importPermission->id]);

        $this->agent = User::factory()->create([
            'role' => 'AGENT',
            'status' => 'Active',
            'bank_id' => $this->bankA->id,
            'department_id' => $this->department->id,
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);
        $this->agent->roles()->sync([$agentRole->id]);
        $this->agent->banks()->attach([$this->bankA->id, $this->bankB->id]);
        $this->agent->departments()->attach([$this->department->id]);
    }

    public function test_super_admin_can_import_with_selected_bank(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $file = UploadedFile::fake()->createWithContent(
            'clients.csv',
            "name,phone,email,account_number\nJohn Doe,+27821112233,john@example.com,ACC123\n"
        );

        $response = $this->postJson('/api/clients/import', [
            'file' => $file,
            'bank_id' => $this->bankB->id,
            'department_ids' => [$this->department->id],
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'upload_id', 'import_batch_number']);

        $upload = ImportUpload::find($response->json('upload_id'));
        $this->assertNotNull($upload);
        $this->assertEquals($this->bankB->id, $upload->bank_id);

        Queue::assertPushed(ImportClientsJob::class, function ($job) {
            $ref = new \ReflectionClass($job);
            $prop = $ref->getProperty('bankId');
            $prop->setAccessible(true);
            return $prop->getValue($job) === $this->bankB->id;
        });
    }

    public function test_super_admin_requires_bank_for_import(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $file = UploadedFile::fake()->createWithContent(
            'clients.csv',
            "name,phone,email,account_number\nJohn Doe,+27821112233,john@example.com,ACC123\n"
        );

        $response = $this->postJson('/api/clients/import', [
            'file' => $file,
            'department_ids' => [$this->department->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_agent_with_multiple_banks_can_import_with_assigned_bank(): void
    {
        Sanctum::actingAs($this->agent);

        $file = UploadedFile::fake()->createWithContent(
            'clients.csv',
            "name,phone,email,account_number\nJane Smith,+27829998877,jane@example.com,ACC456\n"
        );

        $response = $this->postJson('/api/clients/import', [
            'file' => $file,
            'bank_id' => $this->bankB->id,
            'department_ids' => [$this->department->id],
        ]);

        $response->assertOk();

        $upload = ImportUpload::find($response->json('upload_id'));
        $this->assertNotNull($upload);
        $this->assertEquals($this->bankB->id, $upload->bank_id);

        Queue::assertPushed(ImportClientsJob::class, function ($job) {
            $ref = new \ReflectionClass($job);
            $prop = $ref->getProperty('bankId');
            $prop->setAccessible(true);
            return $prop->getValue($job) === $this->bankB->id;
        });
    }

    public function test_agent_cannot_import_with_unassigned_bank(): void
    {
        Sanctum::actingAs($this->agent);

        $file = UploadedFile::fake()->createWithContent(
            'clients.csv',
            "name,phone,email,account_number\nJane Smith,+27829998877,jane@example.com,ACC456\n"
        );

        $response = $this->postJson('/api/clients/import', [
            'file' => $file,
            'bank_id' => $this->bankC->id,
            'department_ids' => [$this->department->id],
        ]);

        $response->assertStatus(403);
    }
}
