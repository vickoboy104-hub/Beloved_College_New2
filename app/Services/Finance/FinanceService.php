<?php

namespace App\Services\Finance;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\FeeInvoice;
use App\Models\FeeItem;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public function __construct(private readonly PaymentSettlementService $settlement) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFeeItem(array $data): FeeItem
    {
        $duplicate = FeeItem::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $data['name']))]);

        foreach (['academic_session_id', 'term_id', 'school_class_id'] as $column) {
            if (filled($data[$column] ?? null)) {
                $duplicate->where($column, $data[$column]);
            } else {
                $duplicate->whereNull($column);
            }
        }

        if ($duplicate->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A matching fee item already exists for the selected session, term, and class.',
            ]);
        }

        return FeeItem::query()->create([
            'name' => trim((string) $data['name']),
            'academic_session_id' => $data['academic_session_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
            'school_class_id' => $data['school_class_id'] ?? null,
            'amount' => $data['amount'],
            'due_date' => $data['due_date'] ?? null,
            'description' => $data['description'] ?? null,
            'is_mandatory' => (bool) ($data['is_mandatory'] ?? true),
        ]);
    }

    public function deleteUnusedFeeItem(FeeItem $feeItem): void
    {
        if ($feeItem->invoices()->exists()) {
            throw ValidationException::withMessages([
                'fee_item' => 'A fee item with invoice history cannot be deleted.',
            ]);
        }

        $feeItem->delete();
    }

    /**
     * @return array{created: int, skipped: int, invoices: Collection<int, FeeInvoice>}
     */
    public function generateInvoices(
        FeeItem $feeItem,
        ?int $studentId = null,
        ?int $schoolClassId = null,
        ?float $amountOverride = null,
        ?string $dueDateOverride = null,
        ?string $notes = null,
    ): array {
        $students = Student::query()
            ->with('user')
            ->whereNull('archived_at')
            ->when($studentId, fn ($query) => $query->whereKey($studentId))
            ->when(! $studentId && $schoolClassId, fn ($query) => $query->where('school_class_id', $schoolClassId))
            ->get();

        if ($students->isEmpty()) {
            throw ValidationException::withMessages([
                'student_id' => 'Select a student or a class with current students.',
            ]);
        }

        $amount = $amountOverride ?? (float) $feeItem->amount;

        if ($amount < 0) {
            throw ValidationException::withMessages(['amount_due' => 'Invoice amount cannot be negative.']);
        }

        $created = collect();
        $skipped = 0;

        DB::transaction(function () use (
            $students,
            $feeItem,
            $amount,
            $dueDateOverride,
            $notes,
            &$created,
            &$skipped,
        ): void {
            foreach ($students as $student) {
                $existing = FeeInvoice::query()
                    ->where('student_id', $student->id)
                    ->where('fee_item_id', $feeItem->id)
                    ->first();

                if ($existing) {
                    $skipped++;

                    continue;
                }

                $created->push(FeeInvoice::query()->create([
                    'invoice_no' => $this->invoiceNumber(),
                    'student_id' => $student->id,
                    'fee_item_id' => $feeItem->id,
                    'amount_due' => $amount,
                    'amount_paid' => 0,
                    'balance' => $amount,
                    'due_date' => $dueDateOverride ?: $feeItem->due_date,
                    'status' => 'unpaid',
                    'issued_at' => now(),
                    'notes' => $notes,
                ]));
            }
        });

        return [
            'created' => $created->count(),
            'skipped' => $skipped,
            'invoices' => $created,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordManualPayment(User $actor, FeeInvoice $invoice, array $data): Payment
    {
        $invoice->loadMissing('student');

        if ((float) $invoice->balance <= 0) {
            throw ValidationException::withMessages([
                'fee_invoice_id' => 'This invoice has already been settled.',
            ]);
        }

        $payment = Payment::query()->create([
            'fee_invoice_id' => $invoice->id,
            'student_id' => $invoice->student_id,
            'provider' => PaymentProvider::Manual,
            'reference' => $this->manualReference(),
            'amount' => $data['amount'],
            'currency' => 'NGN',
            'status' => PaymentStatus::Initialized,
            'channel' => $data['channel'] ?? 'school-office',
            'recorded_by' => $actor->id,
            'note' => $data['note'] ?? 'Recorded manually at the finance desk.',
            'payload' => [
                'source' => 'manual_finance_entry',
                'recorded_amount' => (float) $data['amount'],
                'invoice_balance_before_payment' => (float) $invoice->balance,
                'overpayment_amount' => max((float) $data['amount'] - (float) $invoice->balance, 0),
            ],
        ]);

        return $this->settlement->settle($payment, [
            'channel' => $data['channel'] ?? 'school-office',
            'paid_at' => $data['paid_at'] ?? now(),
            'payload' => ['manual_confirmation' => true],
        ]);
    }

    private function invoiceNumber(): string
    {
        do {
            $number = 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (FeeInvoice::query()->where('invoice_no', $number)->exists());

        return $number;
    }

    private function manualReference(): string
    {
        do {
            $reference = 'MAN-'.Str::upper(Str::random(12));
        } while (Payment::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
