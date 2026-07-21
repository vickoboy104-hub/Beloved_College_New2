<?php

namespace App\Console\Commands;

use App\Services\Migration\StagingRehearsalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

class DeploymentRehearsal extends Command
{
    protected $signature = 'deployment:rehearse
        {rehearsal-id : Stable identifier for this rehearsal cycle}
        {--source= : Legacy/source database connection}
        {--target= : New2/target database connection}
        {--source-snapshot= : Backup or snapshot identifier and timestamp}
        {--operator= : Named operator responsible for this run}
        {--commit= : Exact application commit SHA being rehearsed}
        {--source-disk=* : Legacy file disks to inspect}
        {--target-disk=* : Target file disks to inspect}
        {--max-files=0 : Limit file references for a trial run; zero means complete}
        {--acceptance= : Acceptance evidence JSON path on the report disk}
        {--strict : Fail when technical warnings exist}
        {--require-acceptance : Fail unless all roles and owners approved}';

    protected $description = 'Run and package a read-only staging migration rehearsal with technical and human acceptance evidence.';

    public function handle(StagingRehearsalService $rehearsal): int
    {
        $rehearsalId = trim((string) $this->argument('rehearsal-id'));
        $source = (string) ($this->option('source') ?: config('migration-readiness.source_connection'));
        $target = (string) ($this->option('target') ?: config('migration-readiness.target_connection'));
        $sourceSnapshot = trim((string) $this->option('source-snapshot'));
        $operator = trim((string) $this->option('operator'));
        $commit = trim((string) ($this->option('commit') ?: env('APP_COMMIT_SHA')));
        $sourceDisks = $this->option('source-disk') ?: ['legacy_private', 'legacy_public'];
        $targetDisks = $this->option('target-disk') ?: config('migration-readiness.file_disks', ['local', 'public']);
        $maxFiles = max(0, (int) $this->option('max-files'));

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,100}$/', $rehearsalId)) {
            $this->error('Rehearsal ID must be 3-101 characters using letters, numbers, dots, underscores or hyphens.');

            return self::FAILURE;
        }

        if ($source === $target) {
            $this->error('Source and target database connections must be different.');

            return self::FAILURE;
        }

        foreach ([
            'Source snapshot' => $sourceSnapshot,
            'Operator' => $operator,
            'Application commit' => $commit,
        ] as $label => $value) {
            if ($value === '') {
                $this->error($label.' is required for auditable rehearsal evidence.');

                return self::FAILURE;
            }
        }

        try {
            $acceptance = $this->loadAcceptanceEvidence();
            $report = $rehearsal->run([
                'rehearsal_id' => $rehearsalId,
                'operator' => $operator,
                'application_commit' => $commit,
                'source_snapshot' => $sourceSnapshot,
                'source_connection' => $source,
                'target_connection' => $target,
                'source_disks' => array_values($sourceDisks),
                'target_disks' => array_values($targetDisks),
                'max_files' => $maxFiles,
                'acceptance_evidence' => $acceptance,
                'started_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Evidence', 'Status'], [
            ['Rehearsal ID', $rehearsalId],
            ['Technical checks', strtoupper((string) $report['technical_status'])],
            ['Role and owner acceptance', strtoupper((string) $report['acceptance_status'])],
            ['Cutover eligible', $report['cutover_eligible'] ? 'YES' : 'NO'],
            ['Evidence disk', data_get($report, 'package.disk')],
            ['Evidence directory', data_get($report, 'package.directory')],
            ['Evidence manifest SHA-256', data_get($report, 'package.manifest.sha256')],
        ]);

        foreach ($report['technical_findings'] as $finding) {
            $this->warn('Technical: '.$finding);
        }

        foreach ($report['acceptance_findings'] as $finding) {
            $this->line('Acceptance: '.$finding);
        }

        if ($report['technical_status'] === 'critical') {
            return self::FAILURE;
        }

        if ($this->option('strict') && $report['technical_status'] !== 'pass') {
            return self::FAILURE;
        }

        if ($this->option('require-acceptance') && ! $report['cutover_eligible']) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws JsonException
     */
    private function loadAcceptanceEvidence(): ?array
    {
        $path = trim((string) $this->option('acceptance'));

        if ($path === '') {
            return null;
        }

        $disk = (string) config('migration-readiness.report_disk', 'local');

        if (! Storage::disk($disk)->exists($path)) {
            throw new JsonException('Acceptance evidence file does not exist on '.$disk.': '.$path);
        }

        return json_decode(
            Storage::disk($disk)->get($path),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
