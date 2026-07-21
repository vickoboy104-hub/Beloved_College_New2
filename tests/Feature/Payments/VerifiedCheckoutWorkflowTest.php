<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\FeeInvoice;
use App\Models\FeeItem;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\Payments\PaymentCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerifiedCheckoutWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setMany([
            'enabled_payment_gateways' => 'paystack',
            'paystack_secret_key' => 'sk_test_verified_checkout',
        ], 'payments');
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.test/authorize',
                    'access_code' => 'ACCESS-CODE',
                ],
            ]),
            'https://api.paystack.co/transaction/verify/*' => function (Request $request) {
                $reference = rawurldecode(basename($request->url()));
                $payment = Payment::query()->where('reference', $reference)->firstOrFail();

                return Http::response([
                    'status' => true,
                    'data' => [
                        'status' => 'success',
                        'reference' => $reference,
                        'id' => 90001,
                        'amount' => (int) round(((float) $payment->amount) * 100),
                        'currency' => 'NGN',
                        'channel' => 'card',
                        'paid_at' => now()->toIso8601String(),
                    ],
                ]);
            },
        ]);
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_browser_callback_status_is_ignored_until_server_verification_succeeds(): void
    {
        [$student, $invoice] = $this->invoice('Tuition', 1000);
        $result = app(PaymentCheckoutService::class)->startInvoice(
            $student->user,
            $invoice,
            PaymentProvider::Paystack,
        );
        $payment = $result['payment'];

        $this->assertSame('https://checkout.paystack.test/authorize', $result['authorization_url']);
        $this->assertSame(PaymentStatus::Pending, $payment->status);

        $this->get($this->publicUrl('/payments/callback/paystack?reference='.$payment->reference.'&status=failed'))
            ->assertOk()
            ->assertSee('Payment verified');

        $payment->refresh();
        $invoice->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->receipt_no);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('0.00', $invoice->balance);
    }

    public function test_bundle_checkout_allocates_verified_payment_in_due_order(): void
    {
        [$student, $first] = $this->invoice('Registration', 100, '2026-09-01');
        $secondFee = FeeItem::query()->create([
            'name' => 'Tuition',
            'amount' => 150,
            'is_mandatory' => true,
        ]);
        $second = FeeInvoice::query()->create([
            'invoice_no' => 'INV-BUNDLE-002',
            'student_id' => $student->id,
            'fee_item_id' => $secondFee->id,
            'amount_due' => 150,
            'amount_paid' => 0,
            'balance' => 150,
            'due_date' => '2026-10-01',
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);
        $checkout = app(PaymentCheckoutService::class);
        $result = $checkout->startBundle(
            $student->user,
            [$second->id, $first->id],
            PaymentProvider::Paystack,
        );
        $payment = $checkout->confirm(
            PaymentProvider::Paystack,
            $result['payment']->reference,
        );

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertSame('paid', $second->fresh()->status);
        $this->assertCount(2, data_get($payment->fresh()->payload, 'allocated_invoices'));
    }

    /**
     * @return array{Student, FeeInvoice}
     */
    private function invoice(string $name, float $amount, ?string $dueDate = null): array
    {
        $user = User::factory()->role(UserRole::Student)->create([
            'email' => fake()->unique()->safeEmail(),
        ]);
        $student = Student::query()->create([
            'user_id' => $user->id,
            'admission_no' => 'ADM-26-CHECKOUT-'.fake()->unique()->numerify('###'),
            'status' => 'active',
        ]);
        $feeItem = FeeItem::query()->create([
            'name' => $name.' '.fake()->unique()->numerify('###'),
            'amount' => $amount,
            'is_mandatory' => true,
        ]);
        $invoice = FeeInvoice::query()->create([
            'invoice_no' => 'INV-'.fake()->unique()->numerify('######'),
            'student_id' => $student->id,
            'fee_item_id' => $feeItem->id,
            'amount_due' => $amount,
            'amount_paid' => 0,
            'balance' => $amount,
            'due_date' => $dueDate,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);

        return [$student->load('user'), $invoice];
    }

    private function publicUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.public').$path;
    }
}
