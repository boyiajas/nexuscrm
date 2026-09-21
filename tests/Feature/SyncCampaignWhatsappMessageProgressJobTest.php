<?php

namespace Tests\Feature;

use App\Jobs\SyncCampaignWhatsappMessageProgressJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncCampaignWhatsappMessageProgressJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_progress_jobs_are_coalesced_per_message(): void
    {
        config()->set('queue.default', 'database');

        SyncCampaignWhatsappMessageProgressJob::dispatch(101);
        SyncCampaignWhatsappMessageProgressJob::dispatch(101);
        SyncCampaignWhatsappMessageProgressJob::dispatch(202);

        $this->assertSame(2, Queue::connection('database')->size('whatsapp'));
    }

    public function test_a_new_progress_job_can_be_queued_after_processing_starts(): void
    {
        config()->set('queue.default', 'database');

        SyncCampaignWhatsappMessageProgressJob::dispatch(101);

        $queuedJob = Queue::connection('database')->pop('whatsapp');
        $this->assertNotNull($queuedJob);
        $queuedJob->fire();
        $queuedJob->delete();

        SyncCampaignWhatsappMessageProgressJob::dispatch(101);

        $this->assertSame(1, Queue::connection('database')->size('whatsapp'));
    }
}
