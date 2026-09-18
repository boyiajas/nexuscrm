<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Campaign;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampaignWhatsappMessageCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_list_uses_live_recipient_counts_when_parent_counters_are_stale(): void
    {
        $bank = Bank::query()->create([
            'name' => 'Finchoice',
            'code' => 'finchoice',
            'status' => 'Active',
        ]);

        $user = User::factory()->create([
            'bank_id' => $bank->id,
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);
        $user->roles()->sync([
            Role::query()->where('code', User::ROLE_SUPER_ADMIN)->firstOrFail()->id,
        ]);
        $user->refresh();

        $campaign = Campaign::query()->create([
            'name' => 'WhatsApp count reconciliation',
            'bank_id' => $bank->id,
            'created_by' => $user->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);

        $message = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'created_by_user_id' => $user->id,
            'template_name' => 'count_test',
            'sent_at' => now(),
            'total' => 4,
            'delivered' => 1,
            'failed' => 1,
            'pending' => 0,
            'status' => 'Completed With Failures',
        ]);

        foreach (['Delivered', 'Delivered', 'Failed', 'Sent'] as $index => $status) {
            $client = Client::query()->create([
                'name' => "Recipient {$index}",
                'phone' => '+2782000000' . $index,
                'bank_id' => $bank->id,
            ]);

            CampaignWhatsappRecipient::query()->create([
                'whatsapp_message_id' => $message->id,
                'client_id' => $client->id,
                'phone' => $client->phone,
                'status' => $status,
            ]);
        }

        Sanctum::actingAs($user);

        $this->getJson("/api/campaigns/{$campaign->id}/whatsapp-messages")
            ->assertOk()
            ->assertJsonPath('0.total', 4)
            ->assertJsonPath('0.delivered', 2)
            ->assertJsonPath('0.failed', 1)
            ->assertJsonPath('0.pending', 1);
    }
}
