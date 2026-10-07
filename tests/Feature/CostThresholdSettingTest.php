<?php

namespace Tests\Feature;

use App\Mail\CostThresholdAlertMail;
use App\Models\Bank;
use App\Models\Campaign;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\CostThresholdSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CostThresholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CostThresholdSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_cost_threshold_settings(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $bank = Bank::create(['name' => 'Bank A', 'code' => 'bank_a', 'status' => 'Active']);

        CostThresholdSetting::create([
            'name' => 'Q4 WhatsApp Budget',
            'threshold_amount' => 500.00,
            'bank_ids' => [$bank->id],
            'notification_emails' => ['finance@example.com'],
            'threshold_percentages' => [80, 90, 100],
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/settings/cost-thresholds');

        $response->assertOk()
            ->assertJsonStructure([
                'rules' => [
                    '*' => [
                        'id',
                        'name',
                        'threshold_amount',
                        'bank_ids',
                        'bank_names',
                        'notification_emails',
                        'threshold_percentages',
                        'is_active',
                        'current_month_spend',
                        'spend_percentage',
                        'remaining_budget',
                        'status',
                    ],
                ],
                'summary' => [
                    'total_rules',
                    'active_rules',
                    'total_budget',
                    'total_spend',
                    'overall_percentage',
                ],
                'accessible_banks',
            ]);

        $this->assertCount(1, $response->json('rules'));
        $this->assertSame('Q4 WhatsApp Budget', $response->json('rules.0.name'));
    }

    public function test_user_can_create_cost_threshold_setting_with_multiple_banks_and_percentages(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $bankA = Bank::create(['name' => 'Bank 1', 'code' => 'b1', 'status' => 'Active']);
        $bankB = Bank::create(['name' => 'Bank 2', 'code' => 'b2', 'status' => 'Active']);

        $payload = [
            'name' => 'Monthly Collections Budget',
            'threshold_amount' => 1200.50,
            'bank_ids' => [$bankA->id, $bankB->id],
            'notification_emails' => ['manager@example.com', 'alerts@example.com'],
            'threshold_percentages' => [80, 90, 100],
            'is_active' => true,
            'description' => 'Threshold alerts for primary collection banks.',
        ];

        $response = $this->postJson('/api/settings/cost-thresholds', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('rule.name', 'Monthly Collections Budget')
            ->assertJsonPath('rule.threshold_amount', 1200.50)
            ->assertJsonPath('rule.threshold_percentages', [80, 90, 100]);

        $this->assertDatabaseHas('cost_threshold_settings', [
            'name' => 'Monthly Collections Budget',
            'threshold_amount' => 1200.50,
            'is_active' => true,
        ]);
    }

    public function test_user_cannot_select_banks_outside_their_assigned_portfolio(): void
    {
        $bankAllowed = Bank::create(['name' => 'Allowed Bank', 'code' => 'allowed', 'status' => 'Active']);
        $bankForbidden = Bank::create(['name' => 'Forbidden Bank', 'code' => 'forbidden', 'status' => 'Active']);

        $user = $this->createScopedUser([$bankAllowed->id]);
        Sanctum::actingAs($user);

        // Attempt to create rule targeting forbidden bank
        $response = $this->postJson('/api/settings/cost-thresholds', [
            'name' => 'Illicit Budget',
            'threshold_amount' => 500.00,
            'bank_ids' => [$bankAllowed->id, $bankForbidden->id],
            'notification_emails' => ['user@example.com'],
            'threshold_percentages' => [80, 90, 100],
        ]);

        $response->assertStatus(403);
    }

    public function test_scoped_user_only_sees_rules_for_accessible_banks(): void
    {
        $bankA = Bank::create(['name' => 'Bank A', 'code' => 'b_a', 'status' => 'Active']);
        $bankB = Bank::create(['name' => 'Bank B', 'code' => 'b_b', 'status' => 'Active']);

        CostThresholdSetting::create([
            'name' => 'Rule Bank A',
            'threshold_amount' => 200.00,
            'bank_ids' => [$bankA->id],
            'notification_emails' => ['a@example.com'],
            'threshold_percentages' => [80, 100],
        ]);

        CostThresholdSetting::create([
            'name' => 'Rule Bank B',
            'threshold_amount' => 300.00,
            'bank_ids' => [$bankB->id],
            'notification_emails' => ['b@example.com'],
            'threshold_percentages' => [80, 100],
        ]);

        $user = $this->createScopedUser([$bankA->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/settings/cost-thresholds');

        $response->assertOk();
        $rules = $response->json('rules');
        $this->assertCount(1, $rules);
        $this->assertSame('Rule Bank A', $rules[0]['name']);
    }

    public function test_test_alert_endpoint_sends_email(): void
    {
        Mail::fake();

        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $bank = Bank::create(['name' => 'Bank Test', 'code' => 'b_test', 'status' => 'Active']);

        $rule = CostThresholdSetting::create([
            'name' => 'Test Notification Rule',
            'threshold_amount' => 1000.00,
            'bank_ids' => [$bank->id],
            'notification_emails' => ['test@example.com'],
            'threshold_percentages' => [80, 90, 100],
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/settings/cost-thresholds/{$rule->id}/test-alert");

        $response->assertOk()
            ->assertJsonPath('message', 'Test alert email dispatched successfully.');

        Mail::assertSent(CostThresholdAlertMail::class, function ($mail) {
            return $mail->hasTo('test@example.com') && $mail->isTest === true;
        });
    }

    public function test_evaluation_dispatches_email_when_threshold_is_crossed(): void
    {
        Mail::fake();

        $bank = Bank::create(['name' => 'Spend Bank', 'code' => 'spend_b', 'status' => 'Active']);

        $rule = CostThresholdSetting::create([
            'name' => 'Evaluation Alert Rule',
            'threshold_amount' => 100.00,
            'bank_ids' => [$bank->id],
            'notification_emails' => ['finance@example.com'],
            'threshold_percentages' => [80, 90, 100],
            'is_active' => true,
        ]);

        // Create campaign recipients that generate > $80 spend in current month
        // Marketing rate = 0.0175 per accepted message
        // 5000 messages * 0.0175 = $87.50 (which is 87.5% of $100 budget -> crosses 80% tier)
        $campaign = Campaign::create([
            'name' => 'Spend Campaign',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        $message = CampaignWhatsappMessage::create([
            'campaign_id' => $campaign->id,
            'template_sid' => 'marketing_promo',
            'template_name' => 'marketing_promo',
            'sent_at' => now(),
            'status' => 'Completed',
        ]);

        // Create client for recipient association
        $client = \App\Models\Client::create([
            'bank_id' => $bank->id,
            'name' => 'John Doe',
            'phone' => '+27821110000',
            'status' => 'Active',
        ]);

        // Batch insert 5000 accepted recipients
        $now = now();
        $chunk = [];
        for ($i = 0; $i < 5000; $i++) {
            $chunk[] = [
                'whatsapp_message_id' => $message->id,
                'client_id' => $client->id,
                'phone' => '+27821110000',
                'status' => 'Delivered',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($chunk, 500) as $sub) {
            CampaignWhatsappRecipient::insert($sub);
        }

        $service = app(CostThresholdService::class);
        $result = $service->evaluateRule($rule);

        $this->assertTrue($result['evaluated']);
        $this->assertContains(80, $result['alerts_sent']);
        $this->assertNotContains(90, $result['alerts_sent']);

        Mail::assertSent(CostThresholdAlertMail::class, function ($mail) {
            return $mail->hasTo('finance@example.com') && $mail->percentage === 80;
        });

        // Evaluating again in the same month should NOT re-dispatch 80% alert
        Mail::fake();
        $resultSecond = $service->evaluateRule($rule);
        $this->assertEmpty($resultSecond['alerts_sent']);
        Mail::assertNothingSent();
    }

    public function test_artisan_command_evaluates_thresholds(): void
    {
        Mail::fake();

        $bank = Bank::create(['name' => 'Command Bank', 'code' => 'cmd_b', 'status' => 'Active']);

        CostThresholdSetting::create([
            'name' => 'Command Rule',
            'threshold_amount' => 50.00,
            'bank_ids' => [$bank->id],
            'notification_emails' => ['cmd@example.com'],
            'threshold_percentages' => [80, 100],
            'is_active' => true,
        ]);

        $this->artisan('thresholds:check')
            ->assertExitCode(0);
    }

    private function createAdminUser(): User
    {
        $role = Role::firstOrCreate(
            ['code' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Super Admin', 'is_active' => true]
        );
        $role->update(['is_active' => true]);

        $perm = Permission::firstOrCreate(['code' => 'settings_system'], ['name' => 'System Settings']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = User::create([
            'name' => 'Admin User ' . uniqid(),
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => 'secret123',
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
            'is_super_admin' => true,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function createScopedUser(array $bankIds): User
    {
        $role = Role::firstOrCreate(
            ['code' => User::ROLE_MANAGER],
            ['name' => 'Manager', 'is_active' => true]
        );
        $role->update(['is_active' => true]);

        $perm = Permission::firstOrCreate(['code' => 'settings_system'], ['name' => 'System Settings']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = User::create([
            'name' => 'Scoped User ' . uniqid(),
            'email' => 'scoped_' . uniqid() . '@example.com',
            'password' => 'secret123',
            'role' => User::ROLE_MANAGER,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
            'is_super_admin' => false,
            'bank_id' => $bankIds[0] ?? null,
        ]);
        $user->roles()->attach($role->id);
        $user->banks()->sync($bankIds);

        return $user;
    }
}
