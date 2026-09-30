<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppServiceInterface;
use App\Jobs\SyncWhatsappTemplatesJob;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class WhatsAppTemplateSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->firstOrCreate(
            ['code' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Super Administrator', 'is_system' => true, 'is_active' => true]
        );

        $this->admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);
        $this->admin->roles()->sync([$role->id]);

        Cache::flush();
    }

    public function test_refresh_queues_template_sync_and_returns_immediately(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin->fresh('roles'));

        $this->postJson('/api/whatsapp-templates/sync')
            ->assertStatus(202)
            ->assertJsonPath('status.state', 'queued');

        Queue::assertPushed(SyncWhatsappTemplatesJob::class, function (SyncWhatsappTemplatesJob $job) {
            return $job->requestedBy === $this->admin->id;
        });

        $this->getJson('/api/whatsapp-templates/sync-status')
            ->assertOk()
            ->assertJsonPath('state', 'queued');
    }

    public function test_sync_job_updates_cache_and_reports_completion(): void
    {
        $service = Mockery::mock(WhatsAppServiceInterface::class);
        $service->shouldReceive('getWhatsAppTemplates')
            ->once()
            ->with(false, 100)
            ->andReturn([[
                'sid' => 'payment_reminder',
                'meta_id' => '12345',
                'friendly_name' => 'payment_reminder',
                'language' => 'en_US',
                'preview' => 'Payment reminder',
                'variables' => [],
                'media' => [],
                'buttons' => [],
                'components' => [],
                'whatsapp' => [
                    'status' => 'approved',
                    'category' => 'utility',
                ],
            ]]);

        SyncWhatsappTemplatesJob::markQueued($this->admin->id);
        (new SyncWhatsappTemplatesJob($this->admin->id))->handle($service);

        $this->assertDatabaseHas('whatsapp_templates_cache', [
            'sid' => 'payment_reminder',
            'status' => 'approved',
        ]);
        $this->assertSame('completed', SyncWhatsappTemplatesJob::status()['state']);
        $this->assertSame(1, SyncWhatsappTemplatesJob::status()['count']);
    }
}
