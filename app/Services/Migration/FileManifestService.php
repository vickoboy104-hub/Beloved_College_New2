<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FileManifestService
{
    /**
     * @param  array<int, string>  $disks
     * @return array<string, mixed>
     */
    public function manifest(string $connection, array $disks, int $maxFiles = 0): array
    {
        $database = DB::connection($connection);
        $schema = $database->getSchemaBuilder();
        $pattern = (string) config('migration-readiness.file_column_pattern');
        $entries = [];
        $summary = [
            'references' => 0,
            'found' => 0,
            'missing' => 0,
            'external' => 0,
            'unsafe' => 0,
            'errors' => 0,
            'bytes' => 0,
        ];
        $stoppedEarly = false;

        foreach ($schema->getTables() as $tableDefinition) {
            $table = (string) ($tableDefinition['name'] ?? $tableDefinition['table'] ?? '');

            if ($table === '') {
                continue;
            }

            $columns = collect($schema->getColumns($table))->pluck('name')->all();
            $pathColumns = collect($columns)
                ->filter(fn (string $column) => preg_match($pattern, $column) === 1)
                ->values();

            if ($pathColumns->isEmpty()) {
                continue;
            }

            $identifier = in_array('id', $columns, true) ? 'id' : null;
            $select = collect([$identifier, ...$pathColumns])->filter()->unique()->values()->all();

            foreach ($database->table($table)->select($select)->orderBy($identifier ?: $pathColumns->first())->cursor() as $row) {
                foreach ($pathColumns as $column) {
                    $value = trim((string) data_get($row, $column));

                    if ($value === '') {
                        continue;
                    }

                    $summary['references']++;
                    $entry = [
                        'table' => $table,
                        'record_id' => $identifier ? data_get($row, $identifier) : null,
                        'column' => $column,
                        'stored_path' => $value,
                    ];

                    if (filter_var($value, FILTER_VALIDATE_URL)) {
                        $entries[] = [...$entry, 'status' => 'external'];
                        $summary['external']++;
                    } else {
                        $entries[] = $this->inspectPath($entry, $value, $disks, $summary);
                    }

                    if ($maxFiles > 0 && count($entries) >= $maxFiles) {
                        $stoppedEarly = true;
                        break 3;
                    }
                }
            }
        }

        return [
            'connection' => $connection,
            'disks' => array_values($disks),
            'summary' => $summary,
            'stopped_early' => $stoppedEarly,
            'entries' => $entries,
            'status' => $summary['missing'] > 0 || $summary['unsafe'] > 0 || $summary['errors'] > 0
                ? 'critical'
                : 'pass',
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, string>  $disks
     * @param  array<string, int>  $summary
     * @return array<string, mixed>
     */
    private function inspectPath(array $entry, string $value, array $disks, array &$summary): array
    {
        $normalized = str_replace('\\', '/', ltrim($value, '/'));
        $normalized = preg_replace('#^(storage|public)/#', '', $normalized) ?: $normalized;

        if (str_contains($normalized, '../') || str_starts_with($normalized, '..')) {
            $summary['unsafe']++;

            return [...$entry, 'normalized_path' => $normalized, 'status' => 'unsafe'];
        }

        foreach ($disks as $disk) {
            try {
                if (! Storage::disk($disk)->exists($normalized)) {
                    continue;
                }

                $size = Storage::disk($disk)->size($normalized);
                $stream = Storage::disk($disk)->readStream($normalized);
                $hash = hash_init('sha256');

                if (is_resource($stream)) {
                    hash_update_stream($hash, $stream);
                    fclose($stream);
                }

                $checksum = hash_final($hash);
                $summary['found']++;
                $summary['bytes'] += $size;

                return [
                    ...$entry,
                    'normalized_path' => $normalized,
                    'status' => 'found',
                    'disk' => $disk,
                    'size_bytes' => $size,
                    'mime_type' => Storage::disk($disk)->mimeType($normalized),
                    'sha256' => $checksum,
                ];
            } catch (Throwable $exception) {
                $summary['errors']++;

                return [
                    ...$entry,
                    'normalized_path' => $normalized,
                    'status' => 'error',
                    'disk' => $disk,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $summary['missing']++;

        return [
            ...$entry,
            'normalized_path' => $normalized,
            'status' => 'missing',
        ];
    }
}
