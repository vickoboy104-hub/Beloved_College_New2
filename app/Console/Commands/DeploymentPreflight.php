<?php

namespace App\Console\Commands;

use App\Services\Migration\DeploymentPreflightService;
use App\Services\Migration\MigrationReadinessGuard;
use App\Services\Migration\MigrationReportWriter;
use Illuminate\Console\Command;
use Throwable;

class DeploymentPreflight extends Command
{
    protected $signature = 'deployment:preflight
        {--output= : Report path on the configured report disk}
        {--strict : Return failure for warnings as well as critical findings}';

    protected $description = 'Check application, database, storage, queue, scheduler, mail, hosts and runtime readiness.';

    public function handle(
        MigrationReadinessGuard $guard,
        DeploymentPreflightService $preflight,
        MigrationReportWriter $writer,
    ): int {
        try {
            $guard->assertEnvironmentAllowed();
            $report = $preflight->report();
            $path = $writer->write('deployment-preflight', $report, $this->option('output'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Check', 'Status', 'Message'],
            collect($report['checks'])->map(fn (array $check) => [
                $check['name'],
                strtoupper($check['status']),
                $check['message'],
            ])->all(),
        );
        $this->newLine();
        $this->line('Report: '.$path);

        if ($report['status'] === 'critical') {
            return self::FAILURE;
        }

        return $this->option('strict') && $report['status'] !== 'pass'
            ? self::FAILURE
            : self::SUCCESS;
    }
}
