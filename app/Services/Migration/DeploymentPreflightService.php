<?php

namespace App\Services\Migration;

use App\Models\SystemHeartbeat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeploymentPreflightService
{
    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $checks = [
            $this->appKey(),
            $this->check('debug', ! (bool) config('app.debug'), 'Debug mode is disabled.', 'APP_DEBUG must be false before production cutover.', app()->environment('production')),
            $this->check('https', str_starts_with((string) config('app.url'), 'https://'), 'APP_URL uses HTTPS.', 'APP_URL should use HTTPS in production.', app()->environment('production'), [
                'app_url' => config('app.url'),
            ]),
            $this->hosts(),
            $this->extensions(),
            $this->database(),
            $this->migrations(),
            $this->storage(),
            $this->directoryPermissions(),
            $this->queue(),
            $this->scheduler(),
            $this->session(),
            $this->mail(),
        ];
        $critical = collect($checks)->where('status', 'critical')->count();
        $warnings = collect($checks)->where('status', 'warning')->count();

        return [
            'status' => $critical > 0 ? 'critical' : ($warnings > 0 ? 'warning' : 'pass'),
            'critical_count' => $critical,
            'warning_count' => $warnings,
            'checks' => $checks,
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function check(
        string $name,
        bool $passed,
        string $success,
        string $failure,
        bool $criticalWhenFailed = true,
        array $details = [],
    ): array {
        return [
            'name' => $name,
            'status' => $passed ? 'pass' : ($criticalWhenFailed ? 'critical' : 'warning'),
            'message' => $passed ? $success : $failure,
            'details' => $details,
        ];
    }

    /** @return array<string, mixed> */
    private function appKey(): array
    {
        $key = (string) config('app.key');
        $fingerprint = $key !== '' ? hash('sha256', $key) : null;
        $expected = trim((string) config('migration-readiness.expected_app_key_fingerprint'));
        $matches = $key !== '' && ($expected === '' || hash_equals(strtolower($expected), strtolower((string) $fingerprint)));

        return $this->check(
            'app_key',
            $matches,
            $expected === ''
                ? 'Application encryption key is configured; record and verify its fingerprint before cutover.'
                : 'Application encryption key matches the approved legacy fingerprint.',
            $key === '' ? 'APP_KEY is missing.' : 'APP_KEY does not match the approved legacy fingerprint.',
            true,
            [
                'fingerprint' => $fingerprint,
                'expected_fingerprint_configured' => $expected !== '',
            ],
        );
    }

    /** @return array<string, mixed> */
    private function hosts(): array
    {
        $hosts = array_values((array) config('platform.hosts'));
        $trusted = (array) config('platform.trusted_hosts');
        $missing = array_values(array_diff($hosts, $trusted));
        $testHosts = array_values(array_filter($hosts, fn (string $host) => str_ends_with($host, '.test')));
        $passed = $missing === [] && (! app()->environment('production') || $testHosts === []);

        return $this->check(
            'trusted_hosts',
            $passed,
            'Public, web and app hosts are present in the trusted-host allow-list.',
            $missing !== [] ? 'One or more application hosts are not trusted.' : 'Production still uses local .test hostnames.',
            true,
            compact('hosts', 'trusted', 'missing', 'testHosts'),
        );
    }

    /** @return array<string, mixed> */
    private function extensions(): array
    {
        $required = (array) config('migration-readiness.required_php_extensions', []);
        $missing = array_values(array_filter($required, fn (string $extension) => ! extension_loaded($extension)));

        return $this->check(
            'php_extensions',
            $missing === [],
            'All required PHP extensions are loaded.',
            'Required PHP extensions are missing.',
            true,
            ['required' => $required, 'missing' => $missing, 'php_version' => PHP_VERSION],
        );
    }

    /** @return array<string, mixed> */
    private function database(): array
    {
        try {
            DB::select('select 1');
            $requiredTables = ['users', 'students', 'staff_profiles', 'sessions', 'jobs', 'failed_jobs', 'settings'];
            $missing = array_values(array_filter($requiredTables, fn (string $table) => ! Schema::hasTable($table)));

            return $this->check(
                'database',
                $missing === [],
                'Database responds and required operational tables exist.',
                'Database is missing required operational tables.',
                true,
                ['connection' => config('database.default'), 'missing_tables' => $missing],
            );
        } catch (Throwable $exception) {
            return $this->check('database', false, '', $exception->getMessage(), true);
        }
    }

    /** @return array<string, mixed> */
    private function migrations(): array
    {
        try {
            $migrator = app('migrator');

            if (! $migrator->repositoryExists()) {
                return $this->check('migrations', false, '', 'Migration repository does not exist.', true);
            }

            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $ran = $migrator->getRepository()->getRan();
            $pending = array_values(array_diff(array_keys($files), $ran));

            return $this->check(
                'migrations',
                $pending === [],
                'All application migrations are recorded as executed.',
                'Pending migrations must be reviewed and executed during deployment.',
                true,
                ['pending' => $pending, 'ran_count' => count($ran)],
            );
        } catch (Throwable $exception) {
            return $this->check('migrations', false, '', $exception->getMessage(), true);
        }
    }

    /** @return array<string, mixed> */
    private function storage(): array
    {
        $path = 'preflight/'.str()->uuid().'.txt';

        try {
            Storage::disk('local')->put($path, now()->toIso8601String());
            $passed = Storage::disk('local')->exists($path);
            Storage::disk('local')->delete($path);

            return $this->check('storage', $passed, 'Private storage is readable and writable.', 'Private storage failed its write/read/delete check.', true);
        } catch (Throwable $exception) {
            return $this->check('storage', false, '', $exception->getMessage(), true);
        }
    }

    /** @return array<string, mixed> */
    private function directoryPermissions(): array
    {
        $directories = [storage_path(), base_path('bootstrap/cache')];
        $notWritable = array_values(array_filter($directories, fn (string $directory) => ! is_dir($directory) || ! is_writable($directory)));

        return $this->check(
            'directory_permissions',
            $notWritable === [],
            'Storage and bootstrap cache directories are writable.',
            'Required runtime directories are not writable.',
            true,
            ['not_writable' => $notWritable],
        );
    }

    /** @return array<string, mixed> */
    private function queue(): array
    {
        $connection = (string) config('queue.default');
        $passed = $connection !== 'sync' && Schema::hasTable('jobs') && Schema::hasTable('failed_jobs');

        return $this->check(
            'queue',
            $passed,
            'A persistent queue connection and queue tables are configured.',
            'Production requires a persistent queue connection and queue tables.',
            app()->environment('production'),
            [
                'connection' => $connection,
                'pending_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
                'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function scheduler(): array
    {
        $heartbeat = Schema::hasTable('system_heartbeats')
            ? SystemHeartbeat::query()->where('service', 'scheduler')->first()
            : null;
        $age = $heartbeat?->last_seen_at?->diffInMinutes(now());
        $passed = $age !== null && $age <= 5;

        return $this->check(
            'scheduler',
            $passed,
            'Scheduler heartbeat is current.',
            'No current scheduler heartbeat exists; configure cron to run schedule:run every minute.',
            app()->environment('production'),
            ['last_seen_at' => $heartbeat?->last_seen_at?->toIso8601String(), 'age_minutes' => $age],
        );
    }

    /** @return array<string, mixed> */
    private function session(): array
    {
        $driver = (string) config('session.driver');

        return $this->check(
            'sessions',
            $driver === 'database' && Schema::hasTable('sessions'),
            'Shared database sessions are configured for both portal subdomains.',
            'Database sessions are required for shared web/app portal security.',
            true,
            ['driver' => $driver, 'domain' => config('session.domain')],
        );
    }

    /** @return array<string, mixed> */
    private function mail(): array
    {
        $mailer = (string) config('mail.default');
        $passed = $mailer !== 'smtp' || filled(config('mail.mailers.smtp.host'));

        return $this->check(
            'mail',
            $passed,
            'Mail transport has the minimum required configuration.',
            'SMTP is selected but no host is configured.',
            app()->environment('production'),
            ['mailer' => $mailer, 'from_address' => config('mail.from.address')],
        );
    }
}
