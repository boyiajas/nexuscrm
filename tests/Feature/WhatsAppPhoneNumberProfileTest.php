<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppPhoneNumberProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $adminRole = Role::firstOrCreate(['code' => User::ROLE_SUPER_ADMIN], [
            'name' => 'Super Administrator',
            'is_system' => true,
            'is_active' => true,
        ]);
        $adminRole->update(['is_active' => true]);

        $agentRole = Role::firstOrCreate(['code' => 'agent'], [
            'name' => 'Agent',
            'is_system' => false,
            'is_active' => true,
        ]);

        $department = Department::firstOrCreate(['name' => 'Legal Collections']);

        $this->adminUser = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'department_id' => $department->id,
            'status' => 'Active',
            'password_changed_at' => now(),
        ]);
        $this->adminUser->roles()->sync([$adminRole->id]);

        $this->regularUser = User::factory()->create([
            'role' => 'agent',
            'department_id' => $department->id,
            'status' => 'Active',
            'password_changed_at' => now(),
        ]);
        $this->regularUser->roles()->sync([$agentRole->id]);

        SystemSetting::query()->updateOrCreate(['id' => 1], [
            'meta_app_id' => '347591848299284',
            'meta_app_secret' => 'b4a882dfae52ec3649f0d8da0a9fec44',
            'meta_access_token' => 'mock_meta_access_token_xyz',
            'meta_whatsapp_business_account_id' => '406811385845304',
            'meta_whatsapp_phone_number_id' => '773609549180055',
            'meta_whatsapp_display_phone_number' => '+1 555-835-5133',
        ]);
    }

    public function test_admin_can_fetch_phone_number_profile_and_business_info(): void
    {
        $phoneId = '773609549180055';

        Http::fake([
            "https://graph.facebook.com/v25.0/{$phoneId}?*" => Http::response([
                'id' => $phoneId,
                'display_phone_number' => '+1 555-835-5133',
                'verified_name' => 'Strauss Daly CRM',
                'name_status' => 'APPROVED',
                'new_display_name' => 'Strauss Daly Legal Services',
                'new_name_status' => 'APPROVAL_PENDING',
                'code_verification_status' => 'VERIFIED',
                'quality_rating' => 'GREEN',
                'messaging_limit_tier' => 'TIER_10K',
                'platform_type' => 'CLOUD_API',
            ], 200),
            "https://graph.facebook.com/v25.0/{$phoneId}/whatsapp_business_profile?*" => Http::response([
                'data' => [
                    [
                        'about' => 'Official Legal Communications',
                        'address' => '123 Legal Way, Durban',
                        'description' => 'Dedicated WhatsApp channel for Strauss Daly collections and support.',
                        'email' => 'support@straussdaly.co.za',
                        'profile_picture_url' => 'https://pps.whatsapp.net/v/mock_avatar.jpg',
                        'websites' => ['https://straussdaly.co.za', 'https://sdcrm.co.za'],
                        'vertical' => 'PROF_SERVICES',
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/settings/meta/phone-numbers/{$phoneId}/profile");

        $response->assertOk();
        $response->assertJsonPath('phone_number.verified_name', 'Strauss Daly CRM');
        $response->assertJsonPath('phone_number.new_display_name', 'Strauss Daly Legal Services');
        $response->assertJsonPath('phone_number.new_name_status', 'APPROVAL_PENDING');
        $response->assertJsonPath('business_profile.about', 'Official Legal Communications');
        $response->assertJsonPath('business_profile.email', 'support@straussdaly.co.za');
        $response->assertJsonPath('business_profile.vertical', 'PROF_SERVICES');
        $response->assertJsonPath('business_profile.websites.0', 'https://straussdaly.co.za');
        $response->assertJsonStructure(['vertical_options']);
    }

    public function test_admin_can_submit_new_display_name_to_meta(): void
    {
        $phoneId = '773609549180055';

        Http::fake([
            "https://graph.facebook.com/v25.0/{$phoneId}*" => Http::response([
                'success' => true,
            ], 200),
        ]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson("/api/settings/meta/phone-numbers/{$phoneId}/display-name", [
            'new_display_name' => 'Strauss Daly Customer Care',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Strauss Daly Customer Care', $response->json('message'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'module' => 'Settings',
            'action' => 'Submitted WhatsApp display name to Meta',
        ]);
    }

    public function test_admin_can_upload_profile_picture_to_meta(): void
    {
        $phoneId = '773609549180055';
        $appId = '347591848299284';

        Http::fake([
            "https://graph.facebook.com/v25.0/{$appId}/uploads*" => Http::response([
                'id' => 'upload:123456789_session',
            ], 200),
            "https://graph.facebook.com/v25.0/upload:123456789_session*" => Http::response([
                'h' => '4::mock_profile_picture_handle_xyz==',
            ], 200),
            "https://graph.facebook.com/v25.0/{$phoneId}/whatsapp_business_profile" => Http::response([
                'success' => true,
            ], 200),
        ]);

        Sanctum::actingAs($this->adminUser);

        $file = UploadedFile::fake()->image('logo.png', 640, 640);

        $response = $this->post("/api/settings/meta/phone-numbers/{$phoneId}/profile-picture", [
            'profile_picture' => $file,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Profile picture successfully uploaded', $response->json('message'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'module' => 'Settings',
            'action' => 'Uploaded WhatsApp profile picture to Meta',
        ]);
    }

    public function test_admin_can_update_full_profile_including_display_name_and_websites(): void
    {
        $phoneId = '773609549180055';

        Http::fake([
            "https://graph.facebook.com/v25.0/{$phoneId}*" => Http::response([
                'success' => true,
            ], 200),
            "https://graph.facebook.com/v25.0/{$phoneId}/whatsapp_business_profile" => Http::response([
                'success' => true,
            ], 200),
        ]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson("/api/settings/meta/phone-numbers/{$phoneId}/profile", [
            'new_display_name' => 'Strauss Daly Portal',
            'about' => 'NexusCRM WhatsApp Gateway',
            'address' => '45 Financial Ave, Sandton, Johannesburg',
            'description' => 'Trusted financial and debt advisory communications.',
            'email' => 'contact@straussdaly.co.za',
            'vertical' => 'FINANCE',
            'website_1' => 'straussdaly.co.za', // should be normalized with https://
            'website_2' => 'https://portal.straussdaly.co.za',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Display name', $response->json('message'));
        $this->assertStringContainsString('Business profile details updated', $response->json('message'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'module' => 'Settings',
            'action' => 'Updated WhatsApp Business Profile & Display Name',
        ]);
    }

    public function test_regular_user_is_forbidden_from_managing_waba_number_profile(): void
    {
        $phoneId = '773609549180055';

        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson("/api/settings/meta/phone-numbers/{$phoneId}/profile");
        $response->assertForbidden();

        $response = $this->postJson("/api/settings/meta/phone-numbers/{$phoneId}/profile", [
            'about' => 'Unauthorized attempt',
        ]);
        $response->assertForbidden();
    }
}
