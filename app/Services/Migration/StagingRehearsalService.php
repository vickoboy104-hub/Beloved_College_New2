<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StagingRehearsalService
{
    public function __construct(
        private readonly MigrationReadinessGuard $guard,
        private readonly DatabaseInventoryService $inventory,
        private readonly FinancialReconciliationService $finance,
        private readonly MigrationComparisonService $databaseComparison,
        private readonly FileManifestService $files,
        private readonly FileManifestComparisonService $fileComparison,
        private readonly DeploymentPreflightService $preflight,
        private readonly AcceptanceEvidenceService $acceptance,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function run(array $input): array
    {
        $source = (string) $input['source_connection'];
        $target = (string) $input['target_connection'];
        $this->guard->prepareConnection($source);
        $this->guard->prepareConnection($target);

        $sourceInventory = $this->inventory->inventory($source, false);
        $targetInventory = $this->inventory->inventory($target, false);
        $sourceFinance = $this->finance->reconcile($source);
        $targetFinance = $this->finance->reconcile($target);
        $databaseComparison = $this->databaseComparison->compare(
            $sourceInventory,
            $targetInventory,
            $sourceFinance,
            $targetFinance,
        );
        $sourceFiles = $this->files->manifest(
            $source,
            $input['source_disks'],
            (int) ($input['max_files'] ?? 0),
        );
        $targetFiles = $this->files->manifest(
            $target,
            $input['target_disks'],
            (int) ($input['max_files'] ?? 0),
        );
        $fileComparison = $this->fileComparison->compare($sourceFiles, $targetFiles);
        $preflight = $this->preflight->report();
        $acceptance = $this->acceptance->validate(
            $input['acceptance_evidence'] ?? null,
            (string) $input['rehearsal_id'],
        );
        $technical = $this->technicalStatus(
            $sourceInventory,
            $targetInventory,
            $sourceFinance,
            $targetFinance,
            $databaseComparison,
            $sourceFiles,
            $targetFiles,
            $fileComparison,
            $preflight,
        );
        $cutoverEligible = $technical['status'] === 'pass' && $acceptance['status'] === 'pass';
        $status = match (true) {
            $technical['status'] === 'critical', $acceptance['status'] === 'critical' => 'critical',
            $technical['status'] === 'warning' => 'warning',
            $acceptance['status'] === 'pending' => 'pending',
            $cutoverEligible => 'pass',
            default => 'warning',
        };
        $report = [
            'rehearsal_version' => 1,
            'rehearsal' => [
                'id' => $input['rehearsal_id'],
                'operator' => $input['operator'],
                'application_commit' => $input['application_commit'],
                'source_snapshot' => $input['source_snapshot'],
                'source_connection' => $source,
                'target_connection' => $target,
                'source_disks' => array_values($input['source_disks']),
                'target_disks' => array_values($input['target_disks']),
                'max_files' => (int) ($input['max_files'] ?? 0),
                'started_at' => $input['started_at'] ?? now()->toIso8601String(),
                'completed_at' => now()->toIso8601String(),
            ],
            'status' => $status,
            'technical_status' => $technical['status'],
            'acceptance_status' => $acceptance['status'],
            'cutover_eligible' => $cutoverEligible,
            'technical_findings' => $technical['findings'],
            'acceptance_findings' => $acceptance['findings'],
            'source_inventory' => $sourceInventory,
            'target_inventory' => $targetInventory,
            'source_finance' => $sourceFinance,
            'target_finance' => $targetFinance,
            'database_comparison' => $databaseComparison,
            'source_file_manifest' => $sourceFiles,
            'target_file_manifest' => $targetFiles,
            'file_comparison' => $fileComparison,
            'deployment_preflight' => $preflight,
            'acceptance' => $acceptance,
        ];
        $package = $this->writePackage($report);

        return [
            ...$report,
            'package' => $package,
        ];
    }

    /**
     * @param  array<string, mixed>  $sourceInventory
     * @param  array<string, mixed>  $targetInventory
     * @param  array<string, mixed>  $sourceFinance
     * @param  array<string, mixed>  $targetFinance
     * @param  array<string, mixed>  $databaseComparison
     * @param  array<string, mixed>  $sourceFiles
     * @param  array<string, mixed>  $targetFiles
     * @param  array<string, mixed>  $fileComparison
     * @param  array<string, mixed>  $preflight
     * @return array{status: string, findings: array<int, string>}
     */
    private function technicalStatus(
        array $sourceInventory,
        array $targetInventory,
        array $sourceFinance,
        array $targetFinance,
        array $databaseComparison,
        array $sourceFiles,
        array $targetFiles,
        array $fileComparison,
        array $preflight,
    ): array {
        $critical = [];
        $warnings = [];

        if (($sourceInventory['status'] ?? null) !== 'pass') {
            $warnings[] = 'Source database inventory contains warnings.';
        }

        if (($targetInventory['status'] ?? null) !== 'pass') {
            $warnings[] = 'Target database inventory contains warnings.';
        }

        if (($sourceFinance['status'] ?? null) === 'critical') {
            $critical[] = 'Source invoice equations contain mismatches.';
        }

        if (($targetFinance['status'] ?? null) === 'critical') {
            $critical[] = 'Target invoice equations contain mismatches.';
        }

        if (($databaseComparison['status'] ?? null) !== 'pass') {
            $critical = [...$critical, ...($databaseComparison['critical_findings'] ?? ['Database reconciliation failed.'])];
        }

        if (($sourceFiles['stopped_early'] ?? false) || ($targetFiles['stopped_early'] ?? false)) {
            $critical[] = 'File inspection stopped early and cannot support cutover approval.';
        }

        if (($sourceFiles['status'] ?? null) !== 'pass') {
            $critical[] = 'Source file manifest contains missing, unsafe or unreadable references.';
        }

        if (($targetFiles['status'] ?? null) !== 'pass') {
            $critical[] = 'Target file manifest contains missing, unsafe or unreadable references.';
        }

        if (($fileComparison['status'] ?? null) !== 'pass') {
            $critical = [...$critical, ...($fileComparison['critical_findings'] ?? ['File reconciliation failed.'])];
        }

        if (($preflight['status'] ?? null) === 'critical') {
            $critical[] = 'Deployment preflight contains critical findings.';
        } elseif (($preflight['status'] ?? null) === 'warning') {
            $warnings[] = 'Deployment preflight contains warnings.';
        }

        $critical = array_values(array_unique($critical));
        $warnings = array_values(array_unique($warnings));

        return [
            'status' => $critical !== [] ? 'critical' : ($warnings !== [] ? 'warning' : 'pass'),
            'findings' => [...$critical, ...$warnings],
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function writePackage(array $report): array
    {
        $disk = (string) config('migration-readiness.report_disk', 'local');
        $root = trim((string) config('migration-readiness.report_directory', 'migration-reports'), '/');
        $rehearsalId = Str::slug((string) data_get($report, 'rehearsal.id')) ?: 'rehearsal';
        $version = now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
        $directory = "{$root}/rehearsals/{$rehearsalId}/{$version}";
        $files = [
            'rehearsal-report.json' => json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
            'summary.md' => $this->summaryMarkdown($report),
            'acceptance-evidence.json' => json_encode(
                data_get($report, 'acceptance.evidence'),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
        ];
        $manifest = [];

        foreach ($files as $filename => $contents) {
            $path = $directory.'/'.$filename;
            Storage::disk($disk)->put($path, $contents);
            $manifest[$filename] = [
                'path' => $path,
                'size_bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
            ];
        }

        $manifestContents = json_encode([
            'rehearsal_id' => data_get($report, 'rehearsal.id'),
            'created_at' => now()->toIso8601String(),
            'files' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $manifestPath = $directory.'/evidence-manifest.json';
        Storage::disk($disk)->put($manifestPath, $manifestContents);

        return [
            'disk' => $disk,
            'directory' => $directory,
            'manifest' => [
                'path' => $manifestPath,
                'size_bytes' => strlen($manifestContents),
                'sha256' => hash('sha256', $manifestContents),
            ],
            'files' => $manifest,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function summaryMarkdown(array $report): string
    {
        $technicalFindings = collect($report['technical_findings'] ?? [])
            ->map(fn (string $finding) => '- '.$finding)
            ->implode("\n") ?: '- None';
        $acceptanceFindings = collect($report['acceptance_findings'] ?? [])
            ->map(fn (string $finding) => '- '.$finding)
            ->implode("\n") ?: '- None';

        return implode("\n", [
            '# Staging Rehearsal Summary',
            '',
            '- Rehearsal ID: '.data_get($report, 'rehearsal.id'),
            '- Operator: '.data_get($report, 'rehearsal.operator'),
            '- Application commit: '.data_get($report, 'rehearsal.application_commit'),
            '- Source snapshot: '.data_get($report, 'rehearsal.source_snapshot'),
            '- Source connection: '.data_get($report, 'rehearsal.source_connection'),
            '- Target connection: '.data_get($report, 'rehearsal.target_connection'),
            '- Technical status: '.strtoupper((string) ($report['technical_status'] ?? 'unknown')),
            '- Acceptance status: '.strtoupper((string) ($report['acceptance_status'] ?? 'unknown')),
            '- Cutover eligible: '.(($report['cutover_eligible'] ?? false) ? 'YES' : 'NO'),
            '',
            '## Technical findings',
            '',
            $technicalFindings,
            '',
            '## Acceptance findings',
            '',
            $acceptanceFindings,
            '',
            '## Evidence rule',
            '',
            'This package supports cutover approval only when technical and acceptance statuses are PASS and cutover eligible is YES.',
            '',
        ]);
    }
}
