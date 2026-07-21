<?php

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\FeeItem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinanceOperationsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_fee_creation_invoice_generation_manual_payment_and_overpayment_are_preserved(): void
    {
        $accountant = User::factory()->role(UserRole::Accountant)->create();
        [$session, $class, $student] = $this->context();
        $service = app(FinanceService::class);
        $feeItem = $service->createFeeItem([
            'name' => 'First Term Tuition',
            'academic_session_id' => $session->id,
            'school_class_id' => $class->id,
            'amount' => 100000,
            'due_date' => '2026-10-01',
            'is_mandatory' => true,
        ]);
        $generated = $service->generateInvoices($feeItem, schoolClassId: $class->id);

        $this->assertSame(1, $generated['created']);
        $this->assertSame(0, $generated['skipped']);
        $invoice = $generated['invoices']->firstOrFail();

        $firstPayment = $service->recordManualPayment($accountant, $invoice, [
            'amount' => 40000,
            'channel' => 'bank-transfer',
            'paid_at' => now(),
        ]);

        $invoice->refresh();
        $this->assertSame('part-paid', $invoice->status);
        $this->assertSame('40000.00', $invoice->amount_paid);
        $this->assertSame('60000.00', $invoice->balance);
        $this->assertNotNull($firstPayment->receipt_no);

        $secondPayment = $service->recordManualPayment($accountant, $invoice, [
            'amount' => 70000,
            'channel' => 'cash',
        ]);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('110000.00', $invoice->amount_paid);
        $this->assertSame('0.00', $invoice->balance);
        $this->assertSame(10000.0, (float) data_get($secondPayment->payload, 'overpayment_amount'));

        $rerun = $service->generateInvoices($feeItem, schoolClassId: $class->id);
        $this->assertSame(0, $rerun['created']);
        $this->assertSame(1, $rerun['skipped']);
    }

    public function test_duplicate_fee_scope_is_rejected(): void
    {
        [$session, $class] = $this->context();
        $service = app(FinanceService::class);
        $data = [
            'name' => 'Development Levy',
            'academic_session_id' => $session->id,
            'school_class_id' => $class->id,
            'amount' => 25000,
        ];
        $service->createFeeItem($data);

        $this->expectException(ValidationException::class);
        $service->createFeeItem([...$data, 'name' => 'development levy']);
    }

    /**
     * @return array{AcademicSession, SchoolClass, Student}
     */
    private function context(): array
    {
        $session = AcademicSession::query()->firstOrCreate([
            'name' => '2026/2027',
        ], [
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'promotion_pass_mark' => 40,
            'is_current' => true,
        ]);
        $class = SchoolClass::query()->firstOrCreate([
            'slug' => 'jss-1-finance-operations',
        ], [
            'name' => 'JSS 1',
            'section' => 'A',
        ]);
        $studentUser = User::factory()->role(UserRole::Student)->create();
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'admission_no' => 'ADM-26-FIN-OPS-'.fake()->unique()->numerify('###'),
            'school_class_id' => $class->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        return [$session, $class, $student];
    }
}
