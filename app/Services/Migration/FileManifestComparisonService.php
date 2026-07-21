<?php

namespace App\Services\Migration;

class FileManifestComparisonService
{
    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    public function compare(array $source, array $target): array
    {
        $sourceEntries = $this->index($source['entries'] ?? []);
        $targetEntries = $this->index($target['entries'] ?? []);
        $keys = collect([...array_keys($sourceEntries), ...array_keys($targetEntries)])
            ->unique()
            ->sort()
            ->values();
        $results = [];
        $summary = [
            'references' => $keys->count(),
            'matched' => 0,
            'mismatched' => 0,
            'missing_source_reference' => 0,
            'missing_target_reference' => 0,
            'source_integrity_problems' => 0,
            'target_integrity_problems' => 0,
        ];
        $critical = [];

        foreach ($keys as $key) {
            $sourceEntry = $sourceEntries[$key] ?? null;
            $targetEntry = $targetEntries[$key] ?? null;
            $result = $this->compareEntry($key, $sourceEntry, $targetEntry);
            $results[] = $result;
            $summary[$result['summary_bucket']]++;

            if ($result['status'] === 'critical') {
                $critical[] = $result['message'];
            }
        }

        return [
            'status' => $critical === [] ? 'pass' : 'critical',
            'summary' => $summary,
            'critical_findings' => $critical,
            'entries' => $results,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<string, array<string, mixed>>
     */
    private function index(array $entries): array
    {
        $indexed = [];

        foreach ($entries as $entry) {
            $key = $this->logicalKey($entry);
            $indexed[$key] = $entry;
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>|null  $source
     * @param  array<string, mixed>|null  $target
     * @return array<string, mixed>
     */
    private function compareEntry(string $key, ?array $source, ?array $target): array
    {
        if (! $source) {
            return $this->result(
                $key,
                $source,
                $target,
                'critical',
                'missing_source_reference',
                'Target contains a file reference absent from the source: '.$key.'.',
            );
        }

        if (! $target) {
            return $this->result(
                $key,
                $source,
                $target,
                'critical',
                'missing_target_reference',
                'Source file reference is absent from the target: '.$key.'.',
            );
        }

        if (! in_array($source['status'] ?? null, ['found', 'external'], true)) {
            return $this->result(
                $key,
                $source,
                $target,
                'critical',
                'source_integrity_problems',
                'Source file evidence is not valid for '.$key.' ('.($source['status'] ?? 'unknown').').',
            );
        }

        if (! in_array($target['status'] ?? null, ['found', 'external'], true)) {
            return $this->result(
                $key,
                $source,
                $target,
                'critical',
                'target_integrity_problems',
                'Target file evidence is not valid for '.$key.' ('.($target['status'] ?? 'unknown').').',
            );
        }

        if (($source['status'] ?? null) === 'external' || ($target['status'] ?? null) === 'external') {
            $matched = ($source['status'] ?? null) === 'external'
                && ($target['status'] ?? null) === 'external'
                && ($source['stored_path'] ?? null) === ($target['stored_path'] ?? null);

            return $this->result(
                $key,
                $source,
                $target,
                $matched ? 'pass' : 'critical',
                $matched ? 'matched' : 'mismatched',
                $matched
                    ? 'External file reference matches for '.$key.'.'
                    : 'External file reference mismatch for '.$key.'.',
            );
        }

        $matched = hash_equals((string) ($source['sha256'] ?? ''), (string) ($target['sha256'] ?? ''))
            && (int) ($source['size_bytes'] ?? -1) === (int) ($target['size_bytes'] ?? -2);

        return $this->result(
            $key,
            $source,
            $target,
            $matched ? 'pass' : 'critical',
            $matched ? 'matched' : 'mismatched',
            $matched
                ? 'File checksum and size match for '.$key.'.'
                : 'File checksum or byte-size mismatch for '.$key.'.',
        );
    }

    /**
     * @param  array<string, mixed>|null  $source
     * @param  array<string, mixed>|null  $target
     * @return array<string, mixed>
     */
    private function result(
        string $key,
        ?array $source,
        ?array $target,
        string $status,
        string $bucket,
        string $message,
    ): array {
        return [
            'key' => $key,
            'status' => $status,
            'summary_bucket' => $bucket,
            'message' => $message,
            'source' => $this->evidence($source),
            'target' => $this->evidence($target),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $entry
     * @return array<string, mixed>|null
     */
    private function evidence(?array $entry): ?array
    {
        if (! $entry) {
            return null;
        }

        return collect($entry)
            ->only(['status', 'stored_path', 'normalized_path', 'disk', 'size_bytes', 'mime_type', 'sha256'])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function logicalKey(array $entry): string
    {
        return implode('|', [
            (string) ($entry['table'] ?? 'unknown_table'),
            (string) ($entry['record_id'] ?? 'unknown_record'),
            (string) ($entry['column'] ?? 'unknown_column'),
        ]);
    }
}
