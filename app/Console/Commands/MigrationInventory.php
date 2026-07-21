<?php

namespace App\Console\Commands;

use App\Services\Migration\DatabaseInventoryService;
use App\Services\Migration\FinancialReconciliationService;
use App\Services\Migration\MigrationReadinessGuard;
use App\Services\Migration\MigrationReportWriter;
use Illuminate\Console\Command;
use Throwable;

class MigrationInventory extends Command
{
    protected $signature = 'migration:inventory
        {--connection= : Configured database connection to inspect}
        {--output= : Report path on the configured report disk}
        {--no-schema : Omit detailed schema, index and foreign-key definitions}';

    protected $description = 'Generate a read-only schema, row-count, duplicate, orphan and financial inventory report.';

    public function handle(
        MigrationReadinessGuard $guard,
        DatabaseInventoryService $inventory,
        FinancialReconciliationService $finance,
        MigrationReportWriter $writer,
    ): int {
        $connection = (string) ($this->option('connection') ?: config('database.default'));

        try {
            $guard->prepareConnection($connection);
            $database = $inventory->inventory($connection, ! $this->option('no-schema'));
            $financial = $finance->reconcile($connection);
            $path = $writer->write('database-inventory', [
                'database' => $database,
                'financial' => $financial,
            ], $this->option('output'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Read-only database inventory completed.');
        $this->table(['Metric', 'Value'], [
            ['Connection', $connection],
            ['Tables', $database['table_count']],
            ['Rows', number_format($database['total_rows'])],
            ['Inventory status', $database['status']],
            ['Finance status', $financial['status']],
            ['Report', $path],
        ]);

        return ($database['status'] === 'critical' || $financial['status'] === 'critical')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
