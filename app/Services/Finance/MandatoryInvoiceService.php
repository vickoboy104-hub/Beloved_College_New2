<?php

namespace App\Services\Finance;

use App\Models\AcademicSession;
use App\Models\FeeInvoice;
use App\Models\FeeItem;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MandatoryInvoiceService
{
    /**
     * @return Collection<int, FeeInvoice>
     */
    public function syncForStudent(Student $student): Collection
    {
        $sessionId = $student->academic_session_id
            ?: AcademicSession::query()->where('is_current', true)->value('id');

        $feeItems = FeeItem::query()
            ->where('is_mandatory', true)
            ->where(function ($query) use ($student): void {
                $query
                    ->whereNull('school_class_id')
                    ->orWhere('school_class_id', $student->school_class_id);
            })
            ->where(function ($query) use ($sessionId): void {
                $query
                    ->whereNull('academic_session_id')
                    ->orWhere('academic_session_id', $sessionId);
            })
            ->get();

        return $feeItems->map(function (FeeItem $feeItem) use ($student): FeeInvoice {
            return FeeInvoice::query()->firstOrCreate(
                [
                    'student_id' => $student->id,
                    'fee_item_id' => $feeItem->id,
                ],
                [
                    'invoice_no' => $this->invoiceNumber(),
                    'amount_due' => $feeItem->amount,
                    'amount_paid' => 0,
                    'balance' => $feeItem->amount,
                    'due_date' => $feeItem->due_date,
                    'status' => 'unpaid',
                    'issued_at' => now(),
                    'notes' => 'Auto-generated from mandatory class/session fees.',
                ],
            );
        })->values();
    }

    private function invoiceNumber(): string
    {
        do {
            $number = 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (FeeInvoice::query()->where('invoice_no', $number)->exists());

        return $number;
    }
}
