<?php

namespace Tests\Feature\Migration;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SystemHeartbeat;
use App\Models\User;
use App\Models\WebsiteMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrationReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        config([
            'migration-readiness.report_disk' => 'local',
            'migration-readiness.report_directory' => 'tests',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'queue.default' => 'database',
            'session.driver' => 'database',
            'mail.default' => 'log',
        ]);
    }

    public function test_inventory_command_writes_schema_row_and_finance_report(): void
    {
        $this->artisan('migration:inventory', [
            '--connection' => config('database.default'),
            '--output' => 'tests/inventory.json',
        ])->assertSuccessful();

        Storage::disk('local')->assertExists('tests/inventory.json');
        $report = json_decode(Storage::disk('local')->get('tests/inventory.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('database-inventory', $report['report_type']);
        $this->assertArrayHasKey('users', $report['database']['tables']);
        $this->assertArrayHasKey('financial', $report);
    }

    public function test_file_manifest_records_found_missing_external_and_checksum_values(): void
    {
        Storage::disk('local')->put('public-website/gallery/found.txt', 'Beloved College migration file');
        WebsiteMedia::query()->create([
            'collection' => 'gallery',
            'title' => 'Found file',
            'media_type' => 'document',
            'path' => 'public-website/gallery/found.txt',
            'is_published' => true,
        ]);
        WebsiteMedia::query()->create([
            'collection' => 'gallery',
            'title' => 'Missing file',
            'media_type' => 'document',
            'path' => 'public-website/gallery/missing.txt',
            'is_published' => true,
        ]);

        $this->artisan('migration:files', [
            '--connection' => config('database.default'),
            '--disk' => ['local'],
            '--output' => 'tests/files.json',
        ])->assertSuccessful();

        $report = json_decode(Storage::disk('local')->get('tests/files.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['summary']['found']);
        $this->assertSame(1, $report['summary']['missing']);
        $found = collect($report['entries'])->firstWhere('status', 'found');
        $this->assertSame(hash('sha256', 'Beloved College migration file'), $found['sha256']);
        $this->assertGreaterThan(0, $found['size_bytes']);
    }

    public function test_reconcile_command_can_compare_two_read_only_connections(): void
    {
        config(['database.connections.legacy_test' => config('database.connections.'.config('database.default'))]);
        DB::purge('legacy_test');
        DB::connection('legacy_test')->setPdo(DB::connection()->getPdo());
        DB::connection('legacy_test')->setReadPdo(DB::connection()->getReadPdo());

        $this->artisan('migration:reconcile', [
            '--source' => 'legacy_test',
            '--target' => config('database.default'),
            '--strict' => true,
            '--output' => 'tests/reconcile.json',
        ])->assertSuccessful();

        $report = json_decode(Storage::disk('local')->get('tests/reconcile.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('pass', $report['comparison']['status']);
        $this->assertSame([], $report['comparison']['critical_findings']);
    }

    public function test_preflight_writes_machine_readable_report(): void
    {
        SystemHeartbeat::query()->create([
            'service' => 'scheduler',
            'status' => 'healthy',
            'last_seen_at' => now(),
        ]);

        $this->artisan('deployment:preflight', [
            '--output' => 'tests/preflight.json',
        ])->assertSuccessful();

        $report = json_decode(Storage::disk('local')->get('tests/preflight.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertContains($report['status'], ['pass', 'warning']);
        $this->assertSame(0, $report['critical_count']);
        $this->assertNotEmpty($report['checks']);
    }

    public function test_untrusted_host_is_rejected(): void
    {
        $this->get('http://untrusted.example/dashboard')->assertStatus(400);
    }
}
