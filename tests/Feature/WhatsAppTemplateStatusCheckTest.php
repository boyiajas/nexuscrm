<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppServiceInterface;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsappTemplateCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WhatsAppTemplateStatusCheckTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['code' => User::ROLE_SUPER_ADMIN], [
            'name' => 'Super Administrator',
            'is_system' => true,
            'is_active' => true,
        ]);

        $staffRole = Role::firstOrCreate(['code' => 'STAFF'], [
            'name' => 'Staff Member',
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_changed_at' => now(),
        ]);
        $this->adminUser->roles()->sync([$adminRole->id]);

        $this->staffUser = User::factory()->create([
            'role' => 'STAFF',
            'status' => 'Active',
            'password_changed_at' => now(),
        ]);
        $this->staffUser->roles()->sync([$staffRole->id]);

        SystemSetting::truncate();
        SystemSetting::create([
            'meta_app_id' => '123456789012345',
            'meta_app_secret' => 'test-app-secret',
            'meta_access_token' => 'test-access-token',
            'meta_whatsapp_business_account_id' => '1455412218881488',
            'meta_whatsapp_phone_number_id' => '1247262038476724',
            'meta_whatsapp_display_phone_number' => '+27614776401',
            'meta_webhook_verify_token' => 'test-verify-token',
            'meta_environment' => 'development',
        ]);

        Cache::flush();
    }

    public function test_check_status_fetches_live_from_meta_and_updates_cache(): void
    {
        $cached = WhatsappTemplateCache::create([
            'sid' => 'test_legal_overdue_notice',
            'meta_id' => '998877665544',
            'friendly_name' => 'test_legal_overdue_notice',
            'language' => 'en_US',
            'category' => 'utility',
            'status' => 'PENDING',
            'body_preview' => 'Please pay immediately or legal action will proceed.',
            'raw_whatsapp' => [
                'status' => 'PENDING',
                'category' => 'utility',
            ],
            'synced_at' => now()->subDay(),
        ]);

        $mockWhatsApp = $this->mock(WhatsAppServiceInterface::class);
        $mockWhatsApp->shouldReceive('fetchLiveTemplateFromMeta')
            ->once()
            ->with('998877665544')
            ->andReturn([
                'meta_id' => '998877665544',
                'sid' => 'test_legal_overdue_notice',
                'friendly_name' => 'test_legal_overdue_notice',
                'language' => 'en_US',
                'preview' => 'Please pay immediately or legal action will proceed.',
                'variables' => [],
                'whatsapp' => [
                    'status' => 'APPROVED',
                    'category' => 'marketing',
                    'rejected_reason' => null,
                    'quality_score' => 'GREEN',
                ],
                'rejected_reason' => null,
                'quality_score' => 'GREEN',
                'media' => [],
                'header_format' => null,
                'header_text' => null,
                'footer_text' => null,
                'buttons' => [],
                'components' => [],
            ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/whatsapp-templates/{$cached->sid}/check-status");

        $response->assertOk()
            ->assertJsonPath('message', 'Template status updated from Meta.')
            ->assertJsonPath('live_status', 'APPROVED')
            ->assertJsonPath('quality_score', 'GREEN')
            ->assertJsonPath('template.status', 'APPROVED')
            ->assertJsonPath('template.category', 'marketing');

        $this->assertDatabaseHas('whatsapp_templates_cache', [
            'sid' => 'test_legal_overdue_notice',
            'status' => 'APPROVED',
            'category' => 'marketing',
        ]);
    }

    public function test_check_status_returns_404_when_not_found_on_meta(): void
    {
        $mockWhatsApp = $this->mock(WhatsAppServiceInterface::class);
        $mockWhatsApp->shouldReceive('fetchLiveTemplateFromMeta')
            ->once()
            ->with('non_existent_template')
            ->andReturn(null);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/whatsapp-templates/non_existent_template/check-status');

        $response->assertNotFound()
            ->assertJsonPath('message', "Template 'non_existent_template' was not found on Meta.");
    }

    public function test_check_status_forbidden_for_unauthorized_users(): void
    {
        $response = $this->actingAs($this->staffUser)
            ->postJson('/api/whatsapp-templates/any_template/check-status');

        $response->assertForbidden();
    }

    public function test_to_api_array_surfaces_rejected_reason_and_quality_score(): void
    {
        $template = WhatsappTemplateCache::create([
            'sid' => 'rejected_template_sample',
            'meta_id' => '1122334455',
            'friendly_name' => 'rejected_template_sample',
            'language' => 'en_US',
            'category' => 'utility',
            'status' => 'REJECTED',
            'body_preview' => 'Sample body',
            'raw_whatsapp' => [
                'status' => 'REJECTED',
                'category' => 'utility',
                'rejected_reason' => 'TAG_CONTENT_MISMATCH',
                'quality_score' => 'RED',
            ],
            'synced_at' => now(),
        ]);

        $apiArray = $template->toApiArray();

        $this->assertSame('TAG_CONTENT_MISMATCH', $apiArray['rejected_reason']);
        $this->assertSame('RED', $apiArray['quality_score']);
        $this->assertSame('REJECTED', $apiArray['status']);
    }
}
