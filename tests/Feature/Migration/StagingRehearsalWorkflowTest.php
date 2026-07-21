<?php

namespace Tests\Feature\Migration;

use App\Models\SystemHeartbeat;
use App\Models\WebsiteMedia;
use App\Services\Migration\AcceptanceEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StagingRehearsalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['local', 'public', 'legacy_private', 'legacy_public'] as $disk) {
            Storage::fake($disk);
        }

        config([
            'migration-readiness.report_disk' => 'local',
            'migration-readiness.report_directory' => 'tests',
            'migration-readiness.expected_app_key_fingerprint' => null,
            'app.key' => 'base64:'.base64_encode(str_repeat('r', 32)),
            'app.debug' => false,
            'app.url' => 'https://belovedcollege.test',
            'queue.default' => 'database',
            'session.driver' => 'database',
            'mail.default' => 'log',
        ]);
        config(['database.connections.legacy_test' => config('database.connections.'.config('database.default'))]);
        DB::purge('legacy_test');
        DB::connection('legacy_test')->setPdo(DB::connection()->getPdo());
        DB::connection('legacy_test')->setReadPdo(DB::connection()->getReadPdo());
        SystemHeartbeat::query()->create([
            'service' => 'scheduler',
            'status' => 'healthy',
            'last_seen_at' => now(),
        ]);
        WebsiteMedia::query()->create([
            'collection' => 'gallery',
            'title' => 'Rehearsal evidence',
            'media_type' => 'document',
            'path' => 'public-website/gallery/evidence.txt',
            'is_published' => true,
        ]);
    }

    public function test_initial_rehearsal_packages_technical_pass_and_pending_acceptance(): void
    {
        $this->putMatchingFiles('identical rehearsal evidence');

        $this->artisan('deployment:rehearse', $this->commandOptions('rehearsal-001'))
            ->assertSuccessful();

        $report = $this->reportFor('rehearsal-001');
        $this->assertSame('pass', $report['technical_status']);
        $this->assertSame('pending', $report['acceptance_status']);
        $this->assertFalse($report['cutover_eligible']);
        $this->assertArrayHasKey('student', $report['acceptance']['evidence']['roles']);
        $this->assertArrayHasKey('finance_owner', $report['acceptance']['evidence']['approvals']);
        $this->assertEvidenceManifest('rehearsal-001');
    }

    public function test_complete_acceptance_makes_a_technical_pass_cutover_eligible(): void
    {
        $rehearsalId = 'rehearsal-002';
        $this->putMatchingFiles('approved rehearsal evidence');
        $acceptance = app(AcceptanceEvidenceService::class)->template($rehearsalId);

        foreach ($acceptance['roles'] as $key => $role) {
            $acceptance['roles'][$key] = [
                ...$role,
                'status' => 'pass',
                'tested_by' => 'Acceptance Tester '.$key,
                'tested_at' => now()->toIso8601String(),
                'evidence' => ['screenshots/'.$key.'.png', 'notes/'.$key.'.md'],
            ];
        }

        foreach ($acceptance['approvals'] as $key => $approval) {
            $acceptance['approvals'][$key] = [
                ...$approval,
                'status' => 'approved',
                'approved_by' => 'Approver '.$key,
                'approved_at' => now()->toIso8601String(),
            ];
        }

        Storage::disk('local')->put(
            'tests/completed-acceptance.json',
            json_encode($acceptance, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );

        $this->artisan('deployment:rehearse', [
            ...$this->commandOptions($rehearsalId),
            '--acceptance' => 'tests/completed-acceptance.json',
            '--strict' => true,
            '--require-acceptance' => true,
        ])->assertSuccessful();

        $report = $this->reportFor($rehearsalId);
        $this->assertSame('pass', $report['technical_status']);
        $this->assertSame('pass', $report['acceptance_status']);
        $this->assertTrue($report['cutover_eligible']);
    }

    public function test_checksum_mismatch_blocks_cutover_and_returns_failure(): void
    {
        Storage::disk('legacy_private')->put('public-website/gallery/evidence.txt', 'source bytes');
        Storage::disk('local')->put('public-website/gallery/evidence.txt', 'different target bytes');

        $this->artisan('deployment:rehearse', $this->commandOptions('rehearsal-003'))
            ->assertFailed();

        $report = $this->reportFor('rehearsal-003');
        $this->assertSame('critical', $report['technical_status']);
        $this->assertFalse($report['cutover_eligible']);
        $this->assertSame('critical', $report['file_comparison']['status']);
        $this->assertNotEmpty($report['file_comparison']['critical_findings']);
    }

    public function test_require_acceptance_fails_while_preserving_pending_evidence_package(): void
    {
        $this->putMatchingFiles('pending acceptance evidence');

        $this->artisan('deployment:rehearse', [
            ...$this->commandOptions('rehearsal-004'),
            '--require-acceptance' => true,
        ])->assertFailed();

        $report = $this->reportFor('rehearsal-004');
        $this->assertSame('pass', $report['technical_status']);
        $this->assertSame('pending', $report['acceptance_status']);
        $this->assertFalse($report['cutover_eligible']);
    }

    /**
     * @return array<string, mixed>
     */
    private function commandOptions(string $rehearsalId): array
    {
        return [
            'rehearsal-id' => $rehearsalId,
            '--source' => 'legacy_test',
            '--target' => config('database.default'),
            '--source-snapshot' => 'legacy-backup-20260721T220000Z',
            '--operator' => 'Migration Test Operator',
            '--commit' => str_repeat('a', 40),
            '--source-disk' => ['legacy_private'],
            '--target-disk' => ['local'],
        ];
    }

    private function putMatchingFiles(string $contents): void
    {
        Storage::disk('legacy_private')->put('public-website/gallery/evidence.txt', $contents);
        Storage::disk('local')->put('public-website/gallery/evidence.txt', $contents);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportFor(string $rehearsalId): array
    {
        $path = collect(Storage::disk('local')->allFiles('tests/rehearsals/'.$rehearsalId))
            ->filter(fn (string $path) => str_ends_with($path, '/rehearsal-report.json'))
            ->sort()
            ->last();

        $this->assertNotNull($path, 'Rehearsal report was not written.');

        return json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
    }

    private function assertEvidenceManifest(string $rehearsalId): void
    {
        $manifestPath = collect(Storage::disk('local')->allFiles('tests/rehearsals/'.$rehearsalId))
            ->first(fn (string $path) => str_ends_with($path, '/evidence-manifest.json'));
        $this->assertNotNull($manifestPath);
        $manifest = json_decode(Storage::disk('local')->get($manifestPath), true, flags: JSON_THROW_ON_ERROR);

        foreach ($manifest['files'] as $file) {
            $contents = Storage::disk('local')->get($file['path']);
            $this->assertSame(strlen($contents), $file['size_bytes']);
            $this->assertSame(hash('sha256', $contents), $file['sha256']);
        }
    }
}
