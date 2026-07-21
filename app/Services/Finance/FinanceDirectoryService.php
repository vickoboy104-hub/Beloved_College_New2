<?php

namespace App\Services\Finance;

use App\Enums\PaymentStatus;
use App\Models\AcademicSession;
use App\Models\FeeInvoice;
use App\Models\FeeItem;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FinanceDirectoryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function workspace(array $filters = []): array
    {
        $feeItems = FeeItem::query()
            ->with(['academicSession', 'term', 'schoolClass'])
            ->latest()
            ->get();
        $invoices = FeeInvoice::query()
            ->with(['student.user', 'student.schoolClass', 'feeItem', 'payments'])
            ->latest('issued_at')
            ->get();
        $payments = Payment::query()
            ->with(['student.user', 'student.schoolClass', 'feeInvoice.feeItem', 'recorder'])
            ->latest('paid_at')
            ->get()
            ->reject(fn (Payment $payment) => data_get($payment->payload, 'source') === 'bundle_allocation')
            ->values();
        $students = Student::query()
            ->with(['user', 'schoolClass'])
            ->whereNull('archived_at')
            ->orderBy('admission_no')
            ->get();
        $classes = SchoolClass::query()->orderBy('name')->orderBy('section')->get();

        return [
            'feeItems' => $feeItems,
            'invoices' => $invoices,
            'payments' => $payments,
            'recentPayments' => $payments->take(30),
            'students' => $students,
            'classes' => $classes,
            'sessions' => AcademicSession::query()->latest('start_date')->get(),
            'terms' => Term::query()->with('academicSession')->latest('start_date')->get(),
            'classFeeCatalog' => $this->classFeeCatalog($classes, $feeItems),
            'studentBalanceRows' => $this->studentBalanceRows($invoices, trim((string) ($filters['student_search'] ?? ''))),
            'classBillingRows' => $this->classBillingRows($classes, $students, $invoices),
            'paymentSummary' => $this->paymentSummary($payments),
            'overpaymentRows' => $this->overpaymentRows($invoices),
            'paymentProgressionRows' => $this->paymentProgressionRows($invoices),
            'overview' => $this->overview($feeItems, $invoices, $payments),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function classFeeCatalog(Collection $classes, Collection $feeItems): Collection
    {
        return $classes->map(function (SchoolClass $class) use ($feeItems): array {
            $items = $feeItems
                ->filter(fn (FeeItem $item) => $item->school_class_id === null || $item->school_class_id === $class->id)
                ->sortBy(fn (FeeItem $item) => ($item->term?->name ?? 'ZZZ').' '.$item->name)
                ->values();

            return [
                'class' => $class,
                'items' => $items,
                'total' => round((float) $items->sum('amount'), 2),
            ];
        })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function studentBalanceRows(Collection $invoices, string $search): Collection
    {
        $rows = $invoices
            ->groupBy('student_id')
            ->map(function (Collection $group): array {
                /** @var FeeInvoice $first */
                $first = $group->first();

                return [
                    'student' => $first->student,
                    'unpaid_items' => $group->where('balance', '>', 0)->values(),
                    'progress_items' => $group->where('amount_paid', '>', 0)->values(),
                    'outstanding_total' => (float) $group->sum('balance'),
                    'paid_total' => (float) $group->sum('amount_paid'),
                ];
            })
            ->filter(fn (array $row) => $row['outstanding_total'] > 0 || $row['progress_items']->isNotEmpty());

        if ($search !== '') {
            $words = array_filter(explode(' ', Str::lower($search)));
            $rows = $rows->filter(function (array $row) use ($words): bool {
                $student = $row['student'];
                $haystack = Str::lower(implode(' ', array_filter([
                    $student->user->fullName(),
                    $student->user->email,
                    $student->admission_no,
                    $student->schoolClass?->display_name,
                ])));

                return collect($words)->every(fn (string $word) => str_contains($haystack, $word));
            });
        }

        return $rows->sortByDesc('outstanding_total')->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function classBillingRows(Collection $classes, Collection $students, Collection $invoices): Collection
    {
        return $classes->map(function (SchoolClass $class) use ($students, $invoices): array {
            $classStudents = $students->where('school_class_id', $class->id);
            $classInvoices = $invoices->whereIn('student_id', $classStudents->pluck('id'));
            $expected = (float) $classInvoices->sum('amount_due');
            $collected = (float) $classInvoices->sum('amount_paid');

            return [
                'class' => $class,
                'student_count' => $classStudents->count(),
                'invoice_count' => $classInvoices->count(),
                'students_with_debt' => $classInvoices->where('balance', '>', 0)->pluck('student_id')->unique()->count(),
                'expected_total' => $expected,
                'collected_total' => $collected,
                'outstanding_total' => (float) $classInvoices->sum('balance'),
                'collection_rate' => $expected > 0 ? round(($collected / $expected) * 100, 1) : 0,
            ];
        })->sortByDesc('outstanding_total')->values();
    }

    /**
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    private function paymentSummary(Collection $payments): array
    {
        $paid = $payments->filter(fn (Payment $payment) => $payment->status === PaymentStatus::Paid)->values();

        return [
            'providerBreakdown' => $paid
                ->groupBy(fn (Payment $payment) => $payment->provider->label())
                ->map(fn (Collection $group, string $label) => [
                    'label' => $label,
                    'count' => $group->count(),
                    'total' => (float) $group->sum('amount'),
                ])->sortByDesc('total')->values(),
            'channelBreakdown' => $paid
                ->groupBy(fn (Payment $payment) => Str::headline((string) ($payment->channel ?: 'Unspecified')))
                ->map(fn (Collection $group, string $channel) => [
                    'channel' => $channel,
                    'count' => $group->count(),
                    'total' => (float) $group->sum('amount'),
                ])->sortByDesc('total')->values(),
            'dailyCollection' => $paid
                ->groupBy(fn (Payment $payment) => $payment->paid_at?->format('Y-m-d') ?: 'Unknown')
                ->map(fn (Collection $group, string $day) => [
                    'day' => $day,
                    'count' => $group->count(),
                    'total' => (float) $group->sum('amount'),
                ])->sortByDesc('day')->take(14)->values(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function overpaymentRows(Collection $invoices): Collection
    {
        return $invoices
            ->filter(fn (FeeInvoice $invoice) => (float) $invoice->amount_paid > (float) $invoice->amount_due)
            ->map(fn (FeeInvoice $invoice) => [
                'invoice' => $invoice,
                'student' => $invoice->student,
                'overpayment' => max((float) $invoice->amount_paid - (float) $invoice->amount_due, 0),
                'payment_count' => $invoice->payments->count(),
                'last_payment_at' => $invoice->payments->sortByDesc('paid_at')->first()?->paid_at,
            ])->sortByDesc('overpayment')->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function paymentProgressionRows(Collection $invoices): Collection
    {
        return $invoices
            ->filter(fn (FeeInvoice $invoice) => (float) $invoice->amount_paid > 0 || (float) $invoice->balance > 0)
            ->map(function (FeeInvoice $invoice): array {
                $due = (float) $invoice->amount_due;
                $paid = (float) $invoice->amount_paid;

                return [
                    'invoice' => $invoice,
                    'student' => $invoice->student,
                    'progress' => $due > 0 ? min(round(($paid / $due) * 100, 1), 100) : 0,
                    'overpayment' => max($paid - $due, 0),
                    'last_payment_at' => $invoice->payments->sortByDesc('paid_at')->first()?->paid_at,
                    'recent_payments' => $invoice->payments->sortByDesc('paid_at')->take(3)->values(),
                ];
            })->sortByDesc(fn (array $row) => (float) $row['invoice']->balance)->values();
    }

    /**
     * @return array<string, int|float>
     */
    private function overview(Collection $feeItems, Collection $invoices, Collection $payments): array
    {
        $billed = (float) $invoices->sum('amount_due');
        $collected = (float) $invoices->sum('amount_paid');

        return [
            'fee_item_count' => $feeItems->count(),
            'invoice_count' => $invoices->count(),
            'outstanding_invoice_count' => $invoices->where('balance', '>', 0)->count(),
            'payment_count' => $payments->count(),
            'total_billed' => $billed,
            'total_collected' => $collected,
            'outstanding_total' => (float) $invoices->sum('balance'),
            'overpayment_total' => (float) $invoices->sum(fn (FeeInvoice $invoice) => max((float) $invoice->amount_paid - (float) $invoice->amount_due, 0)),
            'student_debtor_count' => $invoices->where('balance', '>', 0)->pluck('student_id')->unique()->count(),
            'collection_rate' => $billed > 0 ? round(($collected / $billed) * 100, 1) : 0,
        ];
    }
}
