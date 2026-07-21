<?php

namespace App\Console\Commands;

use App\Services\Migration\FileManifestService;
use App\Services\Migration\MigrationReadinessGuard;
use App\Services\Migration\MigrationReportWriter;
use Illuminate\Console\Command;
use Throwable;

class MigrationFileManifest extends Command
{
    protected $signature = 'migration:files
        {--connection= : Database connection whose file references should be inspected}
        {--disk=* : Filesystem disks to search; defaults to configured migration disks}
        {--max-files=0 : Stop after this many references; zero means unlimited}
        {--output= : Report path on the configured report disk}
        {--strict : Return failure when files are missing, unsafe or unreadable}';

    protected $description = 'Generate a read-only manifest with file existence, size, MIME type and SHA-256 checksums.';

    public function handle(
        MigrationReadinessGuard $guard,
        FileManifestService $files,
        MigrationReportWriter $writer,
    ): int {
        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $disks = $this->option('disk') ?: config('migration-readiness.file_disks', ['local', 'public']);
        $maxFiles = max(0, (int) $this->option('max-files'));

        try {
            $guard->prepareConnection($connection);
            $report = $files->manifest($connection, array_values($disks), $maxFiles);
            $path = $writer->write('file-manifest', $report, $this->option('output'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $summary = $report['summary'];
        $this->info('Read-only file manifest completed.');
        $this->table(['Metric', 'Value'], [
            ['Connection', $connection],
            ['References', number_format($summary['references'])],
            ['Found', number_format($summary['found'])],
            ['Missing', number_format($summary['missing'])],
            ['External', number_format($summary['external'])],
            ['Unsafe', number_format($summary['unsafe'])],
            ['Errors', number_format($summary['errors'])],
            ['Bytes found', number_format($summary['bytes'])],
            ['Stopped early', $report['stopped_early'] ? 'Yes' : 'No'],
            ['Report', $path],
        ]);

        return $this->option('strict') && $report['status'] !== 'pass'
            ? self::FAILURE
            : self::SUCCESS;
    }
}
