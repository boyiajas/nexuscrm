<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Campaign;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Models\WhatsappNumberControl;
use App\Services\MetaWhatsAppService;
use App\Services\WhatsAppNumberControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampaignSenderQualityRatingSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_show_returns_live_quality_rating_and_updates_outdated_control_record(): void
    {
        $bank = Bank::query()->create([
            'name' => 'Bank A',
            'code' => 'bank-a',
            'status' => 'Active',
            'primary_whatsapp_number' => '+27670293595',
        ]);

        WhatsappAccount::query()->create([
            'name' => 'FinChoice 2nd Number',
            'bank_id' => $bank->id,
            'app_id' => '123456789',
            'app_secret' => 'test-secret',
            'access_token' => 'test-token',
            'waba_id' => '1339665585898059',
            'phone_number_id' => '1339665585898059',
            'display_phone_number' => '+27 67 029 3595',
            'webhook_verify_token' => 'test-verify',
        ]);

        // Outdated control in database created when the number was paused in the past with RED rating
        $control = WhatsappNumberControl::query()->create([
            'phone_number_id' => '1339665585898059',
            'display_phone_number' => '+27 67 029 3595',
            'normalized_display_phone_number' => '27670293595',
            'quality_rating' => 'RED',
            'is_paused' => true,
            'pause_reason' => 'Paused by administration to allow the number to heal and recover its quality rating.',
            'paused_at' => now()->subDays(5),
        ]);

        $campaign = Campaign::query()->create([
            'name' => 'FINCHOICE HANDOVER SETTLEMENT',
            'bank_id' => $bank->id,
            'whatsapp_from' => '+27 67 029 3595',
            'channels' => ['whatsapp'],
            'status' => 'Active',
        ]);

        // Mock MetaWhatsAppService to return GREEN rating for this sender
        $metaMock = $this->createMock(MetaWhatsAppService::class);
        $metaMock->method('resolveSenderContext')->willReturn([
            'phone_number_id' => '1339665585898059',
            'display_phone_number' => '+27 67 029 3595',
            'quality_rating' => 'GREEN',
        ]);
        $metaMock->method('listWhatsappSenders')->willReturn([
            [
                'phone_number_id' => '1339665585898059',
                'number' => '+27 67 029 3595',
                'quality_rating' => 'GREEN',
            ],
        ]);
        $this->app->instance(MetaWhatsAppService::class, $metaMock);

        $user = $this->createAdminUser($bank);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/campaigns/{$campaign->id}");

        $response->assertOk();
        $senderStatus = $response->json('whatsapp_sender_status');

        $this->assertNotNull($senderStatus);
        $this->assertTrue($senderStatus['is_paused']);
        // Quality rating must reflect the recovered live rating (GREEN), not the stale DB rating (RED)
        $this->assertEquals('GREEN', $senderStatus['quality_rating']);

        // Assert database record was automatically synced from RED to GREEN
        $control->refresh();
        $this->assertEquals('GREEN', $control->quality_rating);
    }

    public function test_meta_whatsapp_service_resolve_sender_context_returns_quality_rating(): void
    {
        $service = $this->getMockBuilder(MetaWhatsAppService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['listWhatsappSenders'])
            ->getMock();

        $service->method('listWhatsappSenders')->willReturn([
            [
                'number' => '+27 67 029 3595',
                'phone_number_id' => '1339665585898059',
                'quality_rating' => 'GREEN',
                'default' => false,
            ],
        ]);

        $resolved = $service->resolveSenderContext('+27 67 029 3595');

        $this->assertEquals('1339665585898059', $resolved['phone_number_id']);
        $this->assertEquals('+27 67 029 3595', $resolved['display_phone_number']);
        $this->assertEquals('GREEN', $resolved['quality_rating']);
    }

    public function test_number_control_service_status_for_resolves_live_rating_when_none_provided(): void
    {
        $metaMock = $this->createMock(MetaWhatsAppService::class);
        $metaMock->method('listWhatsappSenders')->willReturn([
            [
                'phone_number_id' => '1339665585898059',
                'number' => '+27 67 029 3595',
                'quality_rating' => 'GREEN',
            ],
        ]);
        $this->app->instance(MetaWhatsAppService::class, $metaMock);

        // Control starts with stale RED
        $control = WhatsappNumberControl::query()->create([
            'phone_number_id' => '1339665585898059',
            'display_phone_number' => '+27 67 029 3595',
            'normalized_display_phone_number' => '27670293595',
            'quality_rating' => 'RED',
            'is_paused' => true,
        ]);

        $service = app(WhatsAppNumberControlService::class);
        $status = $service->statusFor('1339665585898059', '+27 67 029 3595');

        $this->assertEquals('GREEN', $status['quality_rating']);
        $this->assertEquals('GREEN', $control->fresh()->quality_rating);
    }

    private function createAdminUser(Bank $bank): User
    {
        $role = Role::query()->where('code', User::ROLE_SUPER_ADMIN)->firstOrFail();

        $user = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin-' . Str::uuid() . '@example.test',
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
}
