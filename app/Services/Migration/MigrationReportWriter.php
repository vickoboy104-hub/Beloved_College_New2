<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrationReportWriter
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function write(string $type, array $report, ?string $requestedPath = null): string
    {
        $disk = (string) config('migration-readiness.report_disk', 'local');
        $directory = trim((string) config('migration-readiness.report_directory', 'migration-reports'), '/');
        $path = $requestedPath ?: sprintf(
            '%s/%s-%s-%s.json',
            $directory,
            now()->format('Ymd-His'),
            Str::slug($type),
            Str::lower(Str::random(6)),
        );
        $report = [
            'report_type' => $type,
            'generated_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'application' => config('app.name'),
            'laravel_version' => app()->version(),
            ...$report,
        ];

        Storage::disk($disk)->put(
            $path,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );

        return $disk.':'.$path;
    }
}
