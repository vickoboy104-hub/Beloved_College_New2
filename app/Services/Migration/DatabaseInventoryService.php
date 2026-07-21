<?php

namespace App\Services\Migration;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseInventoryService
{
    /**
     * @return array<string, mixed>
     */
    public function inventory(string $connection, bool $includeSchema = true): array
    {
        $database = DB::connection($connection);
        $schema = $database->getSchemaBuilder();
        $tables = collect($schema->getTables())
            ->map(fn (array $table) => (string) ($table['name'] ?? $table['table'] ?? ''))
            ->filter()
            ->sort()
            ->values();
        $tableReports = [];
        $warnings = [];

        foreach ($tables as $table) {
            try {
                $columns = collect($schema->getColumns($table));
                $foreignKeys = method_exists($schema, 'getForeignKeys')
                    ? collect($schema->getForeignKeys($table))
                    : collect();
                $indexes = method_exists($schema, 'getIndexes')
                    ? collect($schema->getIndexes($table))
                    : collect();
                $tableReports[$table] = [
                    'rows' => $database->table($table)->count(),
                    'columns' => $includeSchema ? $columns->values()->all() : $columns->pluck('name')->values()->all(),
                    'indexes' => $includeSchema ? $indexes->values()->all() : [],
                    'foreign_keys' => $includeSchema ? $foreignKeys->values()->all() : [],
                    'duplicates' => $this->duplicates($database, $table, $columns->pluck('name')->all()),
                    'orphans' => $this->orphans($database, $table, $foreignKeys->all()),
                ];
            } catch (Throwable $exception) {
                $warnings[] = [
                    'table' => $table,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'connection' => $connection,
            'driver' => $database->getDriverName(),
            'database' => (string) $database->getDatabaseName(),
            'table_count' => count($tableReports),
            'total_rows' => collect($tableReports)->sum('rows'),
            'tables' => $tableReports,
            'warnings' => $warnings,
            'status' => $warnings === [] ? 'pass' : 'warning',
        ];
    }

    /**
     * @param  array<int, string>  $availableColumns
     * @return array<string, array<string, mixed>>
     */
    private function duplicates(ConnectionInterface $database, string $table, array $availableColumns): array
    {
        $configured = config("migration-readiness.identity_columns.{$table}", []);
        $report = [];

        foreach ($configured as $column) {
            if (! in_array($column, $availableColumns, true)) {
                continue;
            }

            $groups = $database->table($table)
                ->select($column)
                ->selectRaw('COUNT(*) AS duplicate_count')
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->groupBy($column)
                ->havingRaw('COUNT(*) > 1')
                ->orderByDesc('duplicate_count')
                ->limit(100)
                ->get();

            $report[$column] = [
                'duplicate_groups' => $groups->count(),
                'sample' => $groups->map(fn (object $row) => [
                    'value' => data_get($row, $column),
                    'count' => (int) data_get($row, 'duplicate_count'),
                ])->all(),
            ];
        }

        return $report;
    }

    /**
     * @param  array<int, array<string, mixed>>  $foreignKeys
     * @return array<int, array<string, mixed>>
     */
    private function orphans(ConnectionInterface $database, string $table, array $foreignKeys): array
    {
        $report = [];

        foreach ($foreignKeys as $foreignKey) {
            $columns = $foreignKey['columns'] ?? [];
            $foreignColumns = $foreignKey['foreign_columns'] ?? [];
            $foreignTable = $foreignKey['foreign_table'] ?? null;

            if (count($columns) !== 1 || count($foreignColumns) !== 1 || ! $foreignTable) {
                continue;
            }

            $column = $columns[0];
            $foreignColumn = $foreignColumns[0];
            $count = $database->table("{$table} as child")
                ->leftJoin("{$foreignTable} as parent", "child.{$column}", '=', "parent.{$foreignColumn}")
                ->whereNotNull("child.{$column}")
                ->whereNull("parent.{$foreignColumn}")
                ->count();

            $report[] = [
                'column' => $column,
                'foreign_table' => $foreignTable,
                'foreign_column' => $foreignColumn,
                'orphan_rows' => $count,
            ];
        }

        return $report;
    }
}
