<?php

namespace App\Services\System;

use App\Models\SystemHeartbeat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemHealthService
{
    public function __construct(private readonly MailConfigurationService $mail) {}

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $checks = [
            'database' => $this->database(),
            'storage' => $this->storage(),
            'queue' => $this->queue(),
            'scheduler' => $this->scheduler(),
            'mail' => $this->mail(),
        ];

        $statuses = collect($checks)->pluck('status');
        $overall = $statuses->contains('critical')
            ? 'critical'
            : ($statuses->contains('warning') ? 'warning' : 'healthy');

        return [
            'overall' => $overall,
            'checked_at' => now(),
            'checks' => $checks,
            'environment' => app()->environment(),
            'app_debug' => (bool) config('app.debug'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function database(): array
    {
        $started = microtime(true);

        try {
            DB::select('select 1');

            return [
                'status' => 'healthy',
                'label' => 'Database',
                'message' => 'Database connection is responding.',
                'latency_ms' => round((microtime(true) - $started) * 1000, 1),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'critical',
                'label' => 'Database',
                'message' => $exception->getMessage(),
                'latency_ms' => null,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function storage(): array
    {
        $path = 'health/system-check-'.str()->uuid().'.txt';

        try {
            Storage::disk('local')->put($path, now()->toIso8601String());
            $readable = Storage::disk('local')->exists($path);
            Storage::disk('local')->delete($path);

            return [
                'status' => $readable ? 'healthy' : 'critical',
                'label' => 'Private storage',
                'message' => $readable
                    ? 'Private storage can write, read and delete files.'
                    : 'Storage write completed but the file could not be read.',
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'critical',
                'label' => 'Private storage',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        if (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs')) {
            return [
                'status' => 'critical',
                'label' => 'Queue',
                'message' => 'Queue tables are missing.',
                'pending' => null,
                'failed' => null,
            ];
        }

        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        $oldest = DB::table('jobs')->min('created_at');
        $oldestAgeMinutes = $oldest ? now()->diffInMinutes(now()->setTimestamp((int) $oldest)) : 0;
        $status = $failed > 0 || $oldestAgeMinutes > 15
            ? 'warning'
            : 'healthy';

        return [
            'status' => $status,
            'label' => 'Queue',
            'message' => $status === 'healthy'
                ? 'No failed jobs or stale queued work detected.'
                : 'Review failed jobs or a queue item waiting longer than 15 minutes.',
            'pending' => $pending,
            'failed' => $failed,
            'oldest_age_minutes' => $oldestAgeMinutes,
            'connection' => config('queue.default'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduler(): array
    {
        if (! Schema::hasTable('system_heartbeats')) {
            return [
                'status' => 'critical',
                'label' => 'Scheduler',
                'message' => 'Scheduler heartbeat storage is missing.',
                'last_seen_at' => null,
            ];
        }

        $heartbeat = SystemHeartbeat::query()->where('service', 'scheduler')->first();

        if (! $heartbeat?->last_seen_at) {
            return [
                'status' => 'warning',
                'label' => 'Scheduler',
                'message' => 'No scheduler heartbeat has been recorded yet.',
                'last_seen_at' => null,
            ];
        }

        $ageMinutes = $heartbeat->last_seen_at->diffInMinutes(now());

        return [
            'status' => $ageMinutes <= 5 ? 'healthy' : 'critical',
            'label' => 'Scheduler',
            'message' => $ageMinutes <= 5
                ? 'The scheduler heartbeat is current.'
                : 'The scheduler heartbeat is stale. Confirm cron is running every minute.',
            'last_seen_at' => $heartbeat->last_seen_at,
            'age_minutes' => $ageMinutes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mail(): array
    {
        $status = $this->mail->status();
        $mailer = $status['mailer'];
        $healthy = $mailer !== 'smtp' || $status['smtp_configured'];

        return [
            'status' => $healthy ? 'healthy' : 'warning',
            'label' => 'Mail',
            'message' => $mailer === 'smtp'
                ? ($healthy ? 'SMTP settings are present. Use test delivery to verify the server.' : 'SMTP is selected but required settings are incomplete.')
                : 'Mail is using the '.$mailer.' transport.',
            ...$status,
        ];
    }
}
