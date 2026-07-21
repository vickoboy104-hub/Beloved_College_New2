<?php

namespace App\Services\Migration;

class MigrationComparisonService
{
    /**
     * @param  array<string, mixed>  $sourceInventory
     * @param  array<string, mixed>  $targetInventory
     * @param  array<string, mixed>  $sourceFinance
     * @param  array<string, mixed>  $targetFinance
     * @return array<string, mixed>
     */
    public function compare(
        array $sourceInventory,
        array $targetInventory,
        array $sourceFinance,
        array $targetFinance,
    ): array {
        $sourceTables = $sourceInventory['tables'] ?? [];
        $targetTables = $targetInventory['tables'] ?? [];
        $allTables = collect(array_unique([...array_keys($sourceTables), ...array_keys($targetTables)]))->sort();
        $tableComparison = [];
        $critical = [];

        foreach ($allTables as $table) {
            $sourceRows = data_get($sourceTables, $table.'.rows');
            $targetRows = data_get($targetTables, $table.'.rows');
            $difference = is_numeric($sourceRows) && is_numeric($targetRows)
                ? (int) $targetRows - (int) $sourceRows
                : null;
            $tableComparison[$table] = [
                'source_rows' => $sourceRows,
                'target_rows' => $targetRows,
                'difference' => $difference,
                'status' => $difference === 0 ? 'match' : 'mismatch',
            ];

            if ($difference !== 0) {
                $critical[] = "Row count mismatch for {$table}.";
            }
        }

        $financeComparison = $this->compareFinance($sourceFinance, $targetFinance);
        $critical = [...$critical, ...$financeComparison['critical']];

        return [
            'status' => $critical === [] ? 'pass' : 'critical',
            'source_connection' => $sourceInventory['connection'] ?? null,
            'target_connection' => $targetInventory['connection'] ?? null,
            'tables' => $tableComparison,
            'finance' => $financeComparison['values'],
            'critical_findings' => $critical,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array{values: array<string, mixed>, critical: array<int, string>}
     */
    private function compareFinance(array $source, array $target): array
    {
        if (($source['status'] ?? null) === 'not_applicable' || ($target['status'] ?? null) === 'not_applicable') {
            return [
                'values' => ['status' => 'not_applicable'],
                'critical' => [],
            ];
        }

        $paths = [
            'invoices.count',
            'invoices.amount_due_minor',
            'invoices.amount_paid_minor',
            'invoices.balance_minor',
            'invoices.overpayment_minor',
            'payments.count',
            'payments.amount_minor',
            'payments.successful_count',
            'payments.successful_amount_minor',
            'payments.unallocated_successful_amount_minor',
        ];
        $values = [];
        $critical = [];

        foreach ($paths as $path) {
            $sourceValue = data_get($source, $path);
            $targetValue = data_get($target, $path);
            $difference = is_numeric($sourceValue) && is_numeric($targetValue)
                ? (int) $targetValue - (int) $sourceValue
                : null;
            $values[$path] = [
                'source' => $sourceValue,
                'target' => $targetValue,
                'difference' => $difference,
                'status' => $difference === 0 ? 'match' : 'mismatch',
            ];

            if ($difference !== 0) {
                $critical[] = 'Financial mismatch: '.$path.'.';
            }
        }

        return compact('values', 'critical');
    }
}
