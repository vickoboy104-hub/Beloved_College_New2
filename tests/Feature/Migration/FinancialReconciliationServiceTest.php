<?php

namespace Tests\Feature\Migration;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Migration\FinancialReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_totals_use_exact_minor_units(): void
    {
        $student = $this->student();
        $feeItemId = DB::table('fee_items')->insertGetId([
            'name' => 'Tuition',
            'amount' => '125000.50',
            'is_mandatory' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $invoiceId = DB::table('fee_invoices')->insertGetId([
            'invoice_no' => 'INV-READINESS-001',
            'student_id' => $student->id,
            'fee_item_id' => $feeItemId,
            'amount_due' => '125000.50',
            'amount_paid' => '25000.25',
            'balance' => '100000.25',
            'status' => 'partially_paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('payments')->insert([
            [
                'fee_invoice_id' => $invoiceId,
                'student_id' => $student->id,
                'provider' => 'paystack',
                'reference' => 'PAY-READINESS-001',
                'amount' => '25000.25',
                'currency' => 'NGN',
                'status' => 'successful',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'fee_invoice_id' => null,
                'student_id' => $student->id,
                'provider' => 'manual',
                'reference' => 'PAY-READINESS-002',
                'amount' => '1000.00',
                'currency' => 'NGN',
                'status' => 'initialized',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $report = app(FinancialReconciliationService::class)->reconcile(config('database.default'));

        $this->assertSame('pass', $report['status']);
        $this->assertSame(12500050, $report['invoices']['amount_due_minor']);
        $this->assertSame(2500025, $report['invoices']['amount_paid_minor']);
        $this->assertSame(10000025, $report['invoices']['balance_minor']);
        $this->assertSame(2500025, $report['payments']['successful_amount_minor']);
        $this->assertSame(0, $report['invoices']['equation_mismatches']);
    }

    public function test_invoice_equation_mismatch_is_critical(): void
    {
        $student = $this->student();
        $feeItemId = DB::table('fee_items')->insertGetId([
            'name' => 'Laboratory',
            'amount' => '1000.00',
            'is_mandatory' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('fee_invoices')->insert([
            'invoice_no' => 'INV-READINESS-002',
            'student_id' => $student->id,
            'fee_item_id' => $feeItemId,
            'amount_due' => '1000.00',
            'amount_paid' => '250.00',
            'balance' => '800.00',
            'status' => 'partially_paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(FinancialReconciliationService::class)->reconcile(config('database.default'));

        $this->assertSame('critical', $report['status']);
        $this->assertSame(1, $report['invoices']['equation_mismatches']);
    }

    private function student(): Student
    {
        $class = SchoolClass::query()->create([
            'name' => 'JSS 3',
            'section' => 'A',
            'slug' => 'jss-3-readiness-'.str()->random(5),
        ]);
        $user = User::factory()->role(UserRole::Student)->create();

        return Student::query()->create([
            'user_id' => $user->id,
            'admission_no' => 'ADM-READINESS-'.str()->upper(str()->random(5)),
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);
    }
}
