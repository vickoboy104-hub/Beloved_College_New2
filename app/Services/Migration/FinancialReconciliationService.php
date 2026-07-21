<?php

namespace App\Services\Migration;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class FinancialReconciliationService
{
    private const SUCCESSFUL_PAYMENT_STATUSES = ['paid', 'successful', 'success', 'completed', 'verified'];

    /**
     * @return array<string, mixed>
     */
    public function reconcile(string $connection): array
    {
        $database = DB::connection($connection);
        $schema = $database->getSchemaBuilder();

        if (! $schema->hasTable('fee_invoices') || ! $schema->hasTable('payments')) {
            return [
                'connection' => $connection,
                'status' => 'not_applicable',
                'message' => 'The connection does not contain both fee_invoices and payments tables.',
            ];
        }

        $invoice = $this->invoiceTotals($database);
        $payments = $this->paymentTotals($database);
        $status = $invoice['equation_mismatches'] > 0 ? 'critical' : 'pass';

        return [
            'connection' => $connection,
            'currency_basis' => 'minor units; two decimal places',
            'successful_payment_statuses' => self::SUCCESSFUL_PAYMENT_STATUSES,
            'invoices' => $invoice,
            'payments' => $payments,
            'status' => $status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceTotals(ConnectionInterface $database): array
    {
        $count = 0;
        $amountDue = 0;
        $amountPaid = 0;
        $balance = 0;
        $overpayment = 0;
        $mismatches = 0;
        $statusCounts = [];

        foreach ($database->table('fee_invoices')->orderBy('id')->cursor() as $invoice) {
            $count++;
            $dueMinor = $this->minor(data_get($invoice, 'amount_due'));
            $paidMinor = $this->minor(data_get($invoice, 'amount_paid'));
            $balanceMinor = $this->minor(data_get($invoice, 'balance'));
            $amountDue += $dueMinor;
            $amountPaid += $paidMinor;
            $balance += $balanceMinor;
            $overpayment += min(0, $balanceMinor);
            $status = strtolower((string) data_get($invoice, 'status', 'unknown'));
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            if (($dueMinor - $paidMinor) !== $balanceMinor) {
                $mismatches++;
            }
        }

        ksort($statusCounts);

        return [
            'count' => $count,
            'amount_due_minor' => $amountDue,
            'amount_paid_minor' => $amountPaid,
            'balance_minor' => $balance,
            'overpayment_minor' => abs($overpayment),
            'equation_mismatches' => $mismatches,
            'status_counts' => $statusCounts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentTotals(ConnectionInterface $database): array
    {
        $count = 0;
        $successfulCount = 0;
        $amount = 0;
        $successfulAmount = 0;
        $unallocatedSuccessfulAmount = 0;
        $groups = [];

        foreach ($database->table('payments')->orderBy('id')->cursor() as $payment) {
            $count++;
            $minor = $this->minor(data_get($payment, 'amount'));
            $amount += $minor;
            $status = strtolower((string) data_get($payment, 'status', 'unknown'));
            $provider = strtolower((string) data_get($payment, 'provider', 'unknown'));
            $currency = strtoupper((string) data_get($payment, 'currency', 'NGN'));
            $key = $provider.'|'.$status.'|'.$currency;
            $groups[$key] ??= [
                'provider' => $provider,
                'status' => $status,
                'currency' => $currency,
                'count' => 0,
                'amount_minor' => 0,
            ];
            $groups[$key]['count']++;
            $groups[$key]['amount_minor'] += $minor;

            if (in_array($status, self::SUCCESSFUL_PAYMENT_STATUSES, true)) {
                $successfulCount++;
                $successfulAmount += $minor;

                if (blank(data_get($payment, 'fee_invoice_id'))) {
                    $unallocatedSuccessfulAmount += $minor;
                }
            }
        }

        ksort($groups);

        return [
            'count' => $count,
            'amount_minor' => $amount,
            'successful_count' => $successfulCount,
            'successful_amount_minor' => $successfulAmount,
            'unallocated_successful_amount_minor' => $unallocatedSuccessfulAmount,
            'groups' => array_values($groups),
        ];
    }

    private function minor(mixed $value): int
    {
        $normalized = trim((string) ($value ?? '0'));
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D/', '', $fraction) ?: '', 2, '0'), 0, 2);
        $minor = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$minor : $minor;
    }
}
