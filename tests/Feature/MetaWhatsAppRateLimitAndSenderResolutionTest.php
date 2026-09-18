<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\MetaWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaWhatsAppRateLimitAndSenderResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['code' => User::ROLE_SUPER_ADMIN], [
            'name' => 'Super Administrator',
            'is_system' => true,
            'is_active' => true,
        ]);
        $adminRole->update(['is_active' => true]);

        $this->adminUser = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_changed_at' => now(),
        ]);
        $this->adminUser->roles()->sync([$adminRole->id]);

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

    public function test_get_phone_numbers_caches_results_to_prevent_rate_limiting(): void
    {
        Http::fake([
            'https://graph.facebook.com/v25.0/1455412218881488/phone_numbers*' => Http::response([
                'data' => [
                    [
                        'id' => '1247262038476724',
                        'display_phone_number' => '+27 61 477 6401',
                        'verified_name' => 'Strauss Daly',
                        'quality_rating' => 'GREEN',
                        'code_verification_status' => 'VERIFIED',
                        'name_status' => 'APPROVED',
                        'messaging_limit_tier' => 'TIER_10K',
                        'platform_type' => 'CLOUD_API',
                        'throughput' => ['level' => 'STANDARD'],
                    ],
                ],
            ], 200),
        ]);

        $service = app(MetaWhatsAppService::class);

        // Call 1: makes network request
        $firstResult = $service->getPhoneNumbers();
        $this->assertCount(1, $firstResult);
        $this->assertEquals('1247262038476724', $firstResult[0]['id']);

        // Call 2: should read from Cache without making a second HTTP call
        $secondResult = $service->getPhoneNumbers();
        $this->assertCount(1, $secondResult);
        $this->assertEquals('1247262038476724', $secondResult[0]['id']);

        // Assert only 1 HTTP request was made across both calls
        Http::assertSentCount(1);
    }

    public function test_resolve_sender_context_guards_against_waba_id_as_phone_number_id(): void
    {
        $service = app(MetaWhatsAppService::class);

        // If overrideFrom is explicitly the WABA ID (1455412218881488),
        // it must NOT return the WABA ID as phone_number_id.
        // It should resolve to the configured phone number ID (1247262038476724).
        $context = $service->resolveSenderContext('1455412218881488');

        $this->assertNotEquals('1455412218881488', $context['phone_number_id']);
        $this->assertEquals('1247262038476724', $context['phone_number_id']);
    }

    public function test_settings_validation_rejects_identical_waba_id_and_phone_number_id(): void
    {
        $payload = [
            'meta_whatsapp_business_account_id' => '1455412218881488',
            'meta_whatsapp_phone_number_id' => '1455412218881488', // identical to WABA ID
            'meta_environment' => 'development',
        ];

        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        $response = $this->postJson('/api/settings', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['meta_whatsapp_phone_number_id']);
        $this->assertStringContainsString('cannot be identical', $response->json('message'));
    }

    public function test_whatsapp_account_profile_rejects_identical_waba_id_and_phone_number_id(): void
    {
        $payload = [
            'name' => 'Invalid Profile',
            'app_id' => '123456789012345',
            'app_secret' => 'secret123',
            'access_token' => 'token123',
            'waba_id' => '1455412218881488',
            'phone_number_id' => '1455412218881488', // identical
            'display_phone_number' => '+27614776401',
            'webhook_verify_token' => 'verify123',
        ];

        \Laravel\Sanctum\Sanctum::actingAs($this->adminUser);
        $response = $this->postJson('/api/settings/whatsapp-accounts', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone_number_id']);
    }

    public function test_service_self_heals_when_phone_number_id_is_misconfigured_as_waba_id(): void
    {
        // Simulate production misconfiguration: both WABA ID and Phone Number ID are set to 1455412218881488
        SystemSetting::query()->update([
            'meta_whatsapp_business_account_id' => '1455412218881488',
            'meta_whatsapp_phone_number_id' => '1455412218881488',
        ]);

        // Fake the Meta numbers endpoint returning the actual phone number ID
        Http::fake([
            'https://graph.facebook.com/v25.0/1455412218881488/phone_numbers*' => Http::response([
                'data' => [
                    [
                        'id' => '1247262038476724',
                        'display_phone_number' => '+27 61 477 6401',
                    ],
                ],
            ], 200),
        ]);

        Cache::flush();

        $service = new MetaWhatsAppService();
        $context = $service->resolveSenderContext();

        // Even though settings had the WABA ID, service self-healed to the real phone number ID!
        $this->assertEquals('1247262038476724', $context['phone_number_id']);
        $this->assertEquals('+27 61 477 6401', $context['display_phone_number']);
    }

    public function test_rate_limit_circuit_breaker_prevents_repeated_calls_on_80008(): void
    {
        $callCount = 0;
        Http::fake([
            'https://graph.facebook.com/v25.0/1455412218881488/phone_numbers*' => function () use (&$callCount) {
                $callCount++;
                return Http::response([
                    'error' => [
                        'message' => '(#80008) There have been too many calls to this WhatsApp Business account. Wait a bit and try again.',
                        'type' => 'OAuthException',
                        'code' => 80008,
                    ],
                ], 400);
            },
        ]);

        $service = new MetaWhatsAppService();

        // First call triggers HTTP, encounters 80008, trips circuit breaker, and returns fallback
        $numbers1 = $service->getPhoneNumbers();
        $this->assertEquals(1, $callCount);
        $this->assertCount(1, $numbers1);
        $this->assertEquals('1247262038476724', $numbers1[0]['id']);

        // Subsequent calls within cooldown MUST NOT make another HTTP request to Meta
        $numbers2 = $service->getPhoneNumbers();
        $this->assertEquals(1, $callCount); // Call count remains 1!
        $this->assertEquals('1247262038476724', $numbers2[0]['id']);

        // listWhatsappSenders also uses circuit breaker and fallback smoothly
        $senders = $service->listWhatsappSenders();
        $this->assertEquals(1, $callCount); // Still no new HTTP calls!
        $this->assertNotEmpty($senders);
        $this->assertEquals('1247262038476724', $senders[0]['phone_number_id']);
    }
}
