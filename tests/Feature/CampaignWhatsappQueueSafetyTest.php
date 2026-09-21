<?php

namespace Tests\Feature;

use App\Console\Commands\DispatchCampaignWhatsappMessages;
use App\Contracts\WhatsAppServiceInterface;
use App\Jobs\ProcessCampaignWhatsappRecipientJob;
use App\Jobs\ImportClientsJob;
use App\Jobs\DeleteBatchJob;
use App\Models\Bank;
use App\Models\Campaign;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppSendAttempt;
use App\Services\WhatsAppBatchService;
use App\Services\WhatsAppDailyLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class CampaignWhatsappQueueSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_queue_job_replay_cannot_send_to_meta(): void
    {
        $recipient = $this->recipient('Failed');
        $meta = Mockery::mock(WhatsAppServiceInterface::class);
        $meta->shouldNotReceive('sendTemplateFromSubjectMessage');

        (new ProcessCampaignWhatsappRecipientJob($recipient->id))->handle(
            $meta,
            app(WhatsAppDailyLimitService::class),
            app(WhatsAppBatchService::class),
        );

        $this->assertSame('Failed', $recipient->fresh()->status);
        $this->assertSame(0, WhatsAppSendAttempt::count());
    }

    public function test_a_provider_error_does_not_cause_automatic_resend(): void
    {
        $recipient = $this->recipient('Queued');
        $meta = Mockery::mock(WhatsAppServiceInterface::class);
        $meta->shouldReceive('sendTemplateFromSubjectMessage')
            ->once()
            ->andThrow(new \RuntimeException('Meta API error [500]: uncertain result'));

        $job = new ProcessCampaignWhatsappRecipientJob($recipient->id);
        $batchService = app(WhatsAppBatchService::class);
        $dailyLimitService = app(WhatsAppDailyLimitService::class);

        $job->handle($meta, $dailyLimitService, $batchService);
        $job->handle($meta, $dailyLimitService, $batchService);

        $this->assertSame('Failed', $recipient->fresh()->status);
        $this->assertSame(1, $recipient->fresh()->attempts_count);
        $this->assertSame(1, WhatsAppSendAttempt::count());
    }

    public function test_timeout_callback_does_not_overwrite_an_accepted_send(): void
    {
        $recipient = $this->recipient('Sent');

        (new ProcessCampaignWhatsappRecipientJob($recipient->id))
            ->failed(new \RuntimeException('worker timed out'));

        $this->assertSame('Sent', $recipient->fresh()->status);
    }

    public function test_manual_retry_is_staged_and_dispatch_respects_queue_headroom(): void
    {
        config()->set('queue.default', 'database');

        $recipients = collect(range(1, 3))->map(fn () => $this->recipient('Failed'));
        $message = $recipients->first()->message;
        $recipients->skip(1)->each(fn ($recipient) => $recipient->update([
            'whatsapp_message_id' => $message->id,
        ]));

        $result = app(WhatsAppBatchService::class)->retryFailedRecipients($message);

        $this->assertSame(3, $result['queued_count']);
        $this->assertSame(0, Queue::connection('database')->size('whatsapp'));

        $now = now()->getTimestamp();
        DB::table('jobs')->insert(array_fill(0, 299, [
            'queue' => 'whatsapp',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now,
            'created_at' => $now,
        ]));

        $this->artisan(DispatchCampaignWhatsappMessages::class)->assertExitCode(0);

        $this->assertSame(300, Queue::connection('database')->size('whatsapp'));
        $this->assertSame(2, $message->recipients()->where('status', 'Pending Dispatch')->count());
    }

    public function test_generic_retry_cannot_requeue_campaign_send_jobs(): void
    {
        $role = Role::query()->firstOrCreate(['code' => User::ROLE_SUPER_ADMIN], [
            'name' => 'Super Administrator',
            'is_system' => true,
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => 'Active',
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);
        $user->roles()->sync([$role->id]);
        Sanctum::actingAs($user->refresh());

        $uuid = '4eb189cd-1046-4496-b488-d5bf0d303860';
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'whatsapp',
            'payload' => json_encode(['displayName' => ProcessCampaignWhatsappRecipientJob::class]),
            'exception' => 'test',
            'failed_at' => now(),
        ]);

        $this->postJson('/api/queue-monitor/retry/'.$uuid)->assertStatus(422);
        $this->postJson('/api/queue-monitor/retry/all')->assertStatus(422);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_long_database_worker_reads_import_jobs_from_the_shared_jobs_table(): void
    {
        config()->set('queue.default', 'database');

        ImportClientsJob::dispatch(1, 1, null, [], 'upload.csv', 'upload.csv', 'batch-1', []);
        DeleteBatchJob::dispatch('batch-1', 1);

        $this->assertSame(3900, config('queue.connections.database_long.retry_after'));
        $this->assertSame(2, Queue::connection('database')->size('imports'));
        $this->assertSame(0, Queue::connection('database')->size('default'));
        $this->assertNotNull(Queue::connection('database_long')->pop('imports'));
    }

    private function recipient(string $status): CampaignWhatsappRecipient
    {
        $bank = Bank::query()->create([
            'name' => 'Bank '.uniqid(),
            'code' => uniqid('bank'),
            'status' => 'Active',
        ]);
        $campaign = Campaign::query()->create([
            'name' => 'Queue safety',
            'bank_id' => $bank->id,
            'status' => 'Active',
            'channels' => ['whatsapp'],
        ]);
        $message = CampaignWhatsappMessage::query()->create([
            'campaign_id' => $campaign->id,
            'template_sid' => 'test_template',
            'sent_at' => now(),
            'status' => $status === 'Failed' ? 'Failed' : 'Queued',
            'queued_at' => now(),
        ]);
        $client = Client::query()->create([
            'name' => 'Queue recipient',
            'phone' => '+27821112233',
            'bank_id' => $bank->id,
            'whatsapp_contact_basis' => 'bank_instruction',
        ]);

        return CampaignWhatsappRecipient::query()->create([
            'whatsapp_message_id' => $message->id,
            'client_id' => $client->id,
            'phone' => $client->phone,
            'status' => $status,
        ]);
    }
}
