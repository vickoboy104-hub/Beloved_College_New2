<?php

namespace App\Console\Commands;

use App\Services\Migration\DatabaseInventoryService;
use App\Services\Migration\FinancialReconciliationService;
use App\Services\Migration\MigrationComparisonService;
use App\Services\Migration\MigrationReadinessGuard;
use App\Services\Migration\MigrationReportWriter;
use Illuminate\Console\Command;
use Throwable;

class MigrationReconcile extends Command
{
    protected $signature = 'migration:reconcile
        {--source= : Legacy/source database connection}
        {--target= : New2/target database connection}
        {--output= : Report path on the configured report disk}
        {--strict : Return failure when any row or financial mismatch exists}';

    protected $description = 'Compare source and target row counts plus exact finance totals without modifying either database.';

    public function handle(
        MigrationReadinessGuard $guard,
        DatabaseInventoryService $inventory,
        FinancialReconciliationService $finance,
        MigrationComparisonService $comparison,
        MigrationReportWriter $writer,
    ): int {
        $source = (string) ($this->option('source') ?: config('migration-readiness.source_connection'));
        $target = (string) ($this->option('target') ?: config('migration-readiness.target_connection'));

        if ($source === $target) {
            $this->error('Source and target connections must be different.');

            return self::FAILURE;
        }

        try {
            $guard->prepareConnection($source);
            $guard->prepareConnection($target);
            $sourceInventory = $inventory->inventory($source, false);
            $targetInventory = $inventory->inventory($target, false);
            $sourceFinance = $finance->reconcile($source);
            $targetFinance = $finance->reconcile($target);
            $result = $comparison->compare(
                $sourceInventory,
                $targetInventory,
                $sourceFinance,
                $targetFinance,
            );
            $path = $writer->write('source-target-reconciliation', [
                'source_inventory' => $sourceInventory,
                'target_inventory' => $targetInventory,
                'source_finance' => $sourceFinance,
                'target_finance' => $targetFinance,
                'comparison' => $result,
            ], $this->option('output'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Read-only source-to-target reconciliation completed.');
        $this->table(['Metric', 'Value'], [
            ['Source', $source],
            ['Target', $target],
            ['Status', $result['status']],
            ['Critical findings', count($result['critical_findings'])],
            ['Report', $path],
        ]);

        foreach ($result['critical_findings'] as $finding) {
            $this->warn($finding);
        }

        return $this->option('strict') && $result['status'] !== 'pass'
            ? self::FAILURE
            : self::SUCCESS;
    }
}
