<?php

namespace App\Services;

use App\Models\BankTransferProfile;
use App\Models\BankTransferRun;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BankTransferService
{
    public function adapterInstalled(): bool
    {
        return class_exists(\League\Flysystem\PhpseclibV3\SftpAdapter::class);
    }

    public function testConnection(BankTransferProfile $profile, ?User $user = null): BankTransferRun
    {
        $run = $profile->runs()->create([
            'bank_id' => $profile->bank_id,
            'triggered_by_user_id' => $user?->id,
            'run_type' => 'test',
            'status' => 'running',
            'started_at' => now(),
        ]);

        if (!$this->adapterInstalled()) {
            return $this->finishRun($run, 'failed', 'SFTP adapter is not installed. Run composer install with league/flysystem-sftp-v3 available in the environment.');
        }

        try {
            $disk = Storage::build($this->diskConfig($profile));
            $files = $disk->files($profile->remote_path ?: '.');

            return $this->finishRun($run, 'completed', 'Connection test succeeded.', [
                'files_discovered' => count($files),
                'files_pulled' => 0,
                'files_failed' => 0,
            ]);
        } catch (Throwable $e) {
            return $this->finishRun($run, 'failed', $e->getMessage());
        }
    }

    public function sync(BankTransferProfile $profile, ?User $user = null): BankTransferRun
    {
        $run = $profile->runs()->create([
            'bank_id' => $profile->bank_id,
            'triggered_by_user_id' => $user?->id,
            'run_type' => 'sync',
            'status' => 'running',
            'started_at' => now(),
        ]);

        if (!$this->adapterInstalled()) {
            return $this->finishRun($run, 'failed', 'SFTP adapter is not installed. Run composer install with league/flysystem-sftp-v3 available in the environment.');
        }

        try {
            $disk = Storage::build($this->diskConfig($profile));
            $files = $disk->files($profile->remote_path ?: '.');

            $matched = $this->filterFiles($files, $profile->filename_pattern);

            $profile->forceFill(['last_sync_at' => now()])->save();

            return $this->finishRun($run, 'completed', 'SFTP sync listing completed. Matching files were discovered and are ready for controlled ingestion.', [
                'files_discovered' => count($matched),
                'files_pulled' => 0,
                'files_failed' => 0,
            ]);
        } catch (Throwable $e) {
            return $this->finishRun($run, 'failed', $e->getMessage());
        }
    }

    protected function diskConfig(BankTransferProfile $profile): array
    {
        $config = [
            'driver' => 'sftp',
            'host' => $profile->host,
            'username' => $profile->username,
            'port' => $profile->port ?: 22,
            'root' => $profile->remote_path ?: '.',
            'timeout' => 15,
        ];

        if ($profile->password) {
            $config['password'] = $profile->password;
        }

        if ($profile->private_key) {
            $config['privateKey'] = $profile->private_key;
        }

        return $config;
    }

    protected function filterFiles(array $files, ?string $pattern): array
    {
        if (!$pattern) {
            return $files;
        }

        return array_values(array_filter($files, static fn ($file) => fnmatch($pattern, basename($file))));
    }

    protected function finishRun(BankTransferRun $run, string $status, string $message, array $counts = []): BankTransferRun
    {
        $run->forceFill(array_merge([
            'status' => $status,
            'result_message' => $message,
            'finished_at' => now(),
        ], $counts))->save();

        return $run->fresh(['profile.bank', 'triggerUser']);
    }
}
