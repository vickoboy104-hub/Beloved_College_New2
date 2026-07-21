<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class MigrationReadinessGuard
{
    public function prepareConnection(string $connection): string
    {
        $this->assertEnvironmentAllowed();

        if ($connection === 'legacy' && ! config('database.connections.legacy')) {
            $legacy = config('migration-readiness.legacy_connection');

            if (blank($legacy['database'] ?? null) && blank($legacy['url'] ?? null)) {
                throw new RuntimeException('Legacy database credentials are not configured. Set LEGACY_DB_* values before running source reconciliation.');
            }

            config(['database.connections.legacy' => $legacy]);
            DB::purge('legacy');
        }

        DB::connection($connection)->getPdo();

        return $connection;
    }

    public function assertEnvironmentAllowed(): void
    {
        if (app()->environment('production') && ! config('migration-readiness.allow_production')) {
            throw new RuntimeException(
                'Migration readiness commands are blocked in production. Use a staging copy or explicitly set MIGRATION_READINESS_ALLOW_PRODUCTION=true for a read-only inspection window.',
            );
        }
    }
}
