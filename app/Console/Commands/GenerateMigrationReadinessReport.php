<?php

namespace App\Console\Commands;

use App\Services\Migration\MigrationReadinessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JsonException;

class GenerateMigrationReadinessReport extends Command
{
    protected $signature = 'migration:readiness
        {--no-checksums : Skip SHA-256 file checksums for a faster report}
        {--output= : Custom report path on the configured report disk}
        {--fail-on-warning : Return a failure exit code when warnings are present}';

    protected $description = 'Generate a read-only database, finance, relationship and file reconciliation report.';

    /** @throws JsonException */
    public function handle(MigrationReadinessService $service): int
    {
        $this->components->info('Generating read-only migration readiness report...');
        $report = $service->report(! $this->option('no-checksums'));
        $directory = trim((string) config('migration_readiness.report_directory', 'migration-reports'), '/');
        $path = $this->option('output') ?: $directory.'/readiness-'.now()->format('Ymd-His').'.json';
        $disk = (string) config('migration_readiness.report_disk', 'local');
        Storage::disk($disk)->put(
            $path,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );

        $summary = $report['summary'];
        $this->table(['Status', 'Critical', 'Warnings', 'Report'], [[
            strtoupper((string) $summary['status']),
            $summary['critical_count'],
            $summary['warning_count'],
            $disk.':'.$path,
        ]]);
        $this->line('Database: '.$report['database_driver'].' / '.$report['connection']);
        $this->line('Tables inventoried: '.data_get($report, 'schema.table_count', 0));
        $this->line('Files referenced: '.data_get($report, 'files.record_count', 0));
        $this->line('Missing files: '.data_get($report, 'files.missing_count', 0));
        $this->line('Invoice ledger difference: '.data_get($report, 'finance.invoice_equation_difference_minor', 'n/a').' kobo');
        $this->line('Payment ledger difference: '.data_get($report, 'finance.payment_ledger_difference_minor', 'n/a').' kobo');

        if ($summary['critical_count'] > 0) {
            $this->components->error('Critical reconciliation failures were detected. Do not cut over.');

            return self::FAILURE;
        }

        if ($this->option('fail-on-warning') && $summary['warning_count'] > 0) {
            $this->components->warn('Warnings were detected and --fail-on-warning was requested.');

            return self::FAILURE;
        }

        $this->components->info('Readiness report completed without critical failures.');

        return self::SUCCESS;
    }
}
