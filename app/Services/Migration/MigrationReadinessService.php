<?php

namespace App\Services\Migration;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrationReadinessService
{
    /**
     * @return array<string, mixed>
     */
    public function report(bool $includeChecksums = true): array
    {
        $startedAt = now();
        $schema = $this->schemaInventory();
        $relationships = $this->relationshipChecks();
        $identifiers = $this->duplicateChecks();
        $finance = $this->financeReconciliation();
        $files = $this->fileInventory($includeChecksums);
        $critical = collect([$schema, $relationships, $identifiers, $finance, $files])
            ->flatten(1)
            ->filter(fn (mixed $value) => is_array($value) && ($value['status'] ?? null) === 'critical')
            ->count();
        $warnings = collect([$schema, $relationships, $identifiers, $finance, $files])
            ->flatten(1)
            ->filter(fn (mixed $value) => is_array($value) && ($value['status'] ?? null) === 'warning')
            ->count();

        return [
            'generated_at' => now()->toIso8601String(),
            'duration_ms' => $startedAt->diffInMilliseconds(now()),
            'connection' => DB::getDefaultConnection(),
            'database_driver' => DB::connection()->getDriverName(),
            'environment' => app()->environment(),
            'read_only' => true,
            'summary' => [
                'status' => $critical > 0 ? 'critical' : ($warnings > 0 ? 'warning' : 'ready'),
                'critical_count' => $critical,
                'warning_count' => $warnings,
            ],
            'schema' => $schema,
            'relationships' => $relationships,
            'duplicates' => $identifiers,
            'finance' => $finance,
            'files' => $files,
        ];
    }

    /** @return array<string, mixed> */
    private function schemaInventory(): array
    {
        $tables = [];

        foreach (Schema::getTables() as $table) {
            $name = (string) ($table['name'] ?? $table['table'] ?? '');

            if ($name === '') {
                continue;
            }

            $tables[$name] = [
                'row_count' => DB::table($name)->count(),
                'columns' => collect(Schema::getColumns($name))->map(fn (array $column) => [
                    'name' => $column['name'] ?? null,
                    'type' => $column['type_name'] ?? $column['type'] ?? null,
                    'nullable' => $column['nullable'] ?? null,
                ])->values()->all(),
            ];
        }

        $missing = collect(config('migration_readiness.critical_tables', []))
            ->reject(fn (string $table) => Schema::hasTable($table))
            ->values()
            ->all();

        return [
            'status' => $missing === [] ? 'ready' : 'critical',
            'table_count' => count($tables),
            'missing_critical_tables' => $missing,
            'tables' => $tables,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function relationshipChecks(): array
    {
        return collect(config('migration_readiness.foreign_keys', []))
            ->map(function (array $rule): array {
                $table = $rule['table'];
                $column = $rule['column'];
                $parent = $rule['parent_table'];

                if (! Schema::hasTable($table)
                    || ! Schema::hasTable($parent)
                    || ! Schema::hasColumn($table, $column)) {
                    return [
                        ...$rule,
                        'status' => 'warning',
                        'orphan_count' => null,
                        'message' => 'Table or column unavailable; check skipped.',
                    ];
                }

                $query = DB::table($table.' as child')
                    ->leftJoin($parent.' as parent', 'parent.id', '=', 'child.'.$column)
                    ->whereNotNull('child.'.$column)
                    ->whereNull('parent.id');
                $count = $query->count();

                return [
                    ...$rule,
                    'status' => $count === 0 ? 'ready' : 'critical',
                    'orphan_count' => $count,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function duplicateChecks(): array
    {
        $results = [];

        foreach (config('migration_readiness.unique_identifiers', []) as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                    $results[] = [
                        'table' => $table,
                        'column' => $column,
                        'status' => 'warning',
                        'duplicate_groups' => null,
                    ];
                    continue;
                }

                $groups = DB::table($table)
                    ->select($column, DB::raw('COUNT(*) as aggregate'))
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->groupBy($column)
                    ->havingRaw('COUNT(*) > 1')
                    ->count();
                $results[] = [
                    'table' => $table,
                    'column' => $column,
                    'status' => $groups === 0 ? 'ready' : 'critical',
                    'duplicate_groups' => $groups,
                ];
            }
        }

        return $results;
    }

    /** @return array<string, mixed> */
    private function financeReconciliation(): array
    {
        if (! Schema::hasTable('fee_invoices') || ! Schema::hasTable('payments')) {
            return ['status' => 'warning', 'message' => 'Finance tables are unavailable.'];
        }

        $toMinor = fn (mixed $value): int => (int) round(((float) $value) * 100);
        $invoiceDue = $toMinor(DB::table('fee_invoices')->sum('amount_due'));
        $invoicePaid = $toMinor(DB::table('fee_invoices')->sum('amount_paid'));
        $invoiceBalance = $toMinor(DB::table('fee_invoices')->sum('balance'));
        $settledPayments = $toMinor(DB::table('payments')->where('status', 'paid')->sum('amount'));
        $invoiceEquationDifference = $invoiceDue - $invoicePaid - $invoiceBalance;
        $paymentDifference = $invoicePaid - $settledPayments;
        $negativeBalances = DB::table('fee_invoices')->where('balance', '<', 0)->count();

        return [
            'status' => $invoiceEquationDifference === 0 && $paymentDifference === 0 && $negativeBalances === 0
                ? 'ready'
                : 'critical',
            'currency' => 'NGN',
            'minor_unit' => 'kobo',
            'invoice_count' => DB::table('fee_invoices')->count(),
            'payment_count' => DB::table('payments')->count(),
            'amount_due_minor' => $invoiceDue,
            'amount_paid_minor' => $invoicePaid,
            'balance_minor' => $invoiceBalance,
            'settled_payments_minor' => $settledPayments,
            'invoice_equation_difference_minor' => $invoiceEquationDifference,
            'payment_ledger_difference_minor' => $paymentDifference,
            'negative_balance_count' => $negativeBalances,
            'payments_by_provider' => DB::table('payments')
                ->select('provider', 'status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as amount'))
                ->groupBy('provider', 'status')
                ->get()
                ->map(fn (object $row) => [
                    'provider' => $row->provider,
                    'status' => $row->status,
                    'count' => (int) $row->count,
                    'amount_minor' => $toMinor($row->amount),
                ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function fileInventory(bool $includeChecksums): array
    {
        $records = [];

        foreach (config('migration_readiness.file_sources', []) as $source) {
            $table = $source['table'];
            $column = $source['column'];
            $diskName = $source['disk'] ?? 'local';

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->select(['id', $column])
                ->whereNotNull($column)
                ->orderBy('id')
                ->chunkById(250, function ($rows) use (&$records, $source, $table, $column, $diskName, $includeChecksums): void {
                    foreach ($rows as $row) {
                        $paths = ($source['json'] ?? false)
                            ? Arr::wrap(json_decode((string) $row->{$column}, true) ?: [])
                            : [(string) $row->{$column}];

                        foreach ($paths as $path) {
                            if (! is_string($path) || trim($path) === '') {
                                continue;
                            }

                            $disk = Storage::disk($diskName);
                            $exists = $disk->exists($path);
                            $record = [
                                'table' => $table,
                                'record_id' => $row->id,
                                'column' => $column,
                                'disk' => $diskName,
                                'path' => $path,
                                'exists' => $exists,
                                'size_bytes' => $exists ? $disk->size($path) : null,
                                'mime_type' => $exists ? $disk->mimeType($path) : null,
                                'sha256' => null,
                            ];

                            if ($exists && $includeChecksums) {
                                try {
                                    $stream = $disk->readStream($path);
                                    $context = hash_init('sha256');
                                    hash_update_stream($context, $stream);
                                    fclose($stream);
                                    $record['sha256'] = hash_final($context);
                                } catch (Throwable $exception) {
                                    $record['checksum_error'] = $exception->getMessage();
                                }
                            }

                            $records[] = $record;
                        }
                    }
                });
        }

        $missing = collect($records)->where('exists', false)->count();

        return [
            'status' => $missing === 0 ? 'ready' : 'critical',
            'record_count' => count($records),
            'missing_count' => $missing,
            'total_size_bytes' => collect($records)->sum('size_bytes'),
            'checksums_included' => $includeChecksums,
            'records' => $records,
        ];
    }
}
