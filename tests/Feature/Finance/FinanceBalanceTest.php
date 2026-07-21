<?php

namespace Tests\Feature\Finance;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\FeeInvoice;
use App\Models\FeeItem;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_balance_moves_from_unpaid_to_part_paid_and_paid(): void
    {
        $student = $this->student();
        $feeItem = $this->feeItem('Tuition', 1000);
        $invoice = $this->invoice($student, $feeItem, 'INV-001', 1000);

        $invoice->syncBalance();
        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertSame('1000.00', $invoice->fresh()->balance);

        Payment::query()->create([
            'fee_invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'provider' => PaymentProvider::Manual,
            'reference' => 'PAY-001',
            'amount' => 400,
            'currency' => 'NGN',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $invoice->syncBalance();
        $this->assertSame('part-paid', $invoice->fresh()->status);
        $this->assertSame('400.00', $invoice->fresh()->amount_paid);
        $this->assertSame('600.00', $invoice->fresh()->balance);

        Payment::query()->create([
            'fee_invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'provider' => PaymentProvider::Manual,
            'reference' => 'PAY-002',
            'amount' => 600,
            'currency' => 'NGN',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $invoice->syncBalance();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('1000.00', $invoice->fresh()->amount_paid);
        $this->assertSame('0.00', $invoice->fresh()->balance);
    }

    public function test_grouped_payment_is_allocated_by_due_date_without_exceeding_balances(): void
    {
        $student = $this->student();
        $firstFee = $this->feeItem('Registration', 100);
        $secondFee = $this->feeItem('Tuition', 150);

        $firstInvoice = $this->invoice($student, $firstFee, 'INV-101', 100, '2026-09-01');
        $secondInvoice = $this->invoice($student, $secondFee, 'INV-102', 150, '2026-10-01');

        $bundle = Payment::query()->create([
            'fee_invoice_id' => null,
            'student_id' => $student->id,
            'provider' => PaymentProvider::Paystack,
            'reference' => 'BUNDLE-001',
            'gateway_reference' => 'GATEWAY-001',
            'amount' => 180,
            'currency' => 'NGN',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'payload' => [
                'invoice_ids' => [$secondInvoice->id, $firstInvoice->id],
            ],
        ]);

        $bundle->allocateBundleInvoices();

        $this->assertSame('paid', $firstInvoice->fresh()->status);
        $this->assertSame('0.00', $firstInvoice->fresh()->balance);
        $this->assertSame('part-paid', $secondInvoice->fresh()->status);
        $this->assertSame('80.00', $secondInvoice->fresh()->amount_paid);
        $this->assertSame('70.00', $secondInvoice->fresh()->balance);
        $this->assertTrue((bool) data_get($bundle->fresh()->payload, 'bundle_allocated'));
        $this->assertCount(2, data_get($bundle->fresh()->payload, 'allocated_invoices'));
    }

    private function student(): Student
    {
        $user = User::factory()->role(UserRole::Student)->create();

        return Student::query()->create([
            'user_id' => $user->id,
            'admission_no' => 'BC/'.fake()->unique()->numerify('#####'),
            'status' => 'active',
        ]);
    }

    private function feeItem(string $name, float $amount): FeeItem
    {
        return FeeItem::query()->create([
            'name' => $name,
            'amount' => $amount,
            'is_mandatory' => true,
        ]);
    }

    private function invoice(
        Student $student,
        FeeItem $feeItem,
        string $invoiceNumber,
        float $amount,
        ?string $dueDate = null,
    ): FeeInvoice {
        return FeeInvoice::query()->create([
            'invoice_no' => $invoiceNumber,
            'student_id' => $student->id,
            'fee_item_id' => $feeItem->id,
            'amount_due' => $amount,
            'amount_paid' => 0,
            'balance' => $amount,
            'due_date' => $dueDate,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);
    }
}
