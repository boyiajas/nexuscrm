<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class QueueMonitorController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->hasRole('SUPER_ADMIN')) {
            abort(403, 'Unauthorized.');
        }

        $pendingJobsCount = DB::table('jobs')->count();
        $failedJobsCount = DB::table('failed_jobs')->count();
        
        $oldestJob = DB::table('jobs')->orderBy('created_at', 'asc')->first();
        
        $isQueueRunning = true;
        if ($oldestJob) {
            // If oldest job is older than 5 minutes (300 seconds)
            if (time() - $oldestJob->created_at > 300) {
                $isQueueRunning = false;
            }
        }

        // Check process using ps
        $output = shell_exec('ps aux | grep "[q]ueue:work"');
        $isWorkerRunning = !empty($output);
        
        if (!$isWorkerRunning && $pendingJobsCount > 0) {
            $statusColor = 'danger';
            $statusMessage = 'Queue worker is NOT running and there are pending jobs!';
        } elseif (!$isWorkerRunning) {
            $statusColor = 'warning';
            $statusMessage = 'Queue worker is NOT running, but no jobs are currently pending.';
        } elseif ($pendingJobsCount > 0 && !$isQueueRunning) {
            $statusColor = 'danger';
            $statusMessage = 'Queue worker is running, but jobs appear to be stuck (oldest job > 5 mins).';
        } else {
            $statusColor = 'success';
            $statusMessage = 'Queue worker is running optimally.';
        }

        $recentPending = DB::table('jobs')->orderBy('created_at', 'asc')->limit(50)->get()->map(function ($job) {
            $payload = json_decode($job->payload, true);
            $name = $payload['displayName'] ?? 'Unknown Job';
            // Simplify name
            $name = class_basename($name);
            return [
                'id' => $job->id,
                'queue' => $job->queue,
                'name' => $name,
                'attempts' => $job->attempts,
                'created_at' => Carbon::createFromTimestamp($job->created_at)->toDateTimeString(),
            ];
        });

        $recentFailed = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->limit(50)->get()->map(function ($job) {
            $payload = json_decode($job->payload, true);
            $name = $payload['displayName'] ?? 'Unknown Job';
            $name = class_basename($name);
            return [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'queue' => $job->queue,
                'name' => $name,
                'exception' => mb_substr($job->exception, 0, 150) . '...',
                'failed_at' => $job->failed_at,
            ];
        });

        return response()->json([
            'stats' => [
                'pending' => $pendingJobsCount,
                'failed' => $failedJobsCount,
            ],
            'status' => [
                'color' => $statusColor,
                'message' => $statusMessage,
                'worker_active' => $isWorkerRunning,
            ],
            'recent_pending' => $recentPending,
            'recent_failed' => $recentFailed,
        ]);
    }

    public function retry(Request $request, $id)
    {
        if (!$request->user()->hasRole('SUPER_ADMIN')) {
            abort(403, 'Unauthorized.');
        }

        if ($id === 'all') {
            Artisan::call('queue:retry', ['id' => 'all']);
        } else {
            Artisan::call('queue:retry', ['id' => $id]);
        }

        return response()->json(['message' => 'Job(s) queued for retry.']);
    }

    public function deleteFailed(Request $request, $id)
    {
        if (!$request->user()->hasRole('SUPER_ADMIN')) {
            abort(403, 'Unauthorized.');
        }

        if ($id === 'all') {
            Artisan::call('queue:forget', ['id' => 'all']);
        } else {
            Artisan::call('queue:forget', ['id' => $id]);
        }

        return response()->json(['message' => 'Failed job(s) deleted.']);
    }
}
