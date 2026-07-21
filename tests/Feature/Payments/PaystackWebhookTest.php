<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\FeeInvoice;
use App\Models\FeeItem;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_signed_webhook_is_server_verified_and_idempotent(): void
    {
        Setting::setMany([
            'paystack_secret_key' => 'sk_test_webhook',
            'paystack_webhook_secret' => 'webhook-secret',
        ], 'payments');
        [$invoice, $payment] = $this->pendingPayment();
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $payment->reference,
                    'id' => 81234,
                    'amount' => 500000,
                    'currency' => 'NGN',
                    'channel' => 'bank_transfer',
                    'paid_at' => now()->toIso8601String(),
                ],
            ]),
        ]);
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'id' => 81234,
                'reference' => $payment->reference,
            ],
        ];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha512', $raw, 'webhook-secret');
        $server = [
            'HTTP_HOST' => config('platform.hosts.public'),
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ];

        $this->call('POST', '/webhooks/paystack', [], [], [], $server, $raw)
            ->assertOk();
        $this->call('POST', '/webhooks/paystack', [], [], [], $server, $raw)
            ->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, PaymentEvent::query()->count());
        $this->assertSame('processed', PaymentEvent::query()->firstOrFail()->status);
        $this->assertSame(1, Payment::query()->where('reference', $payment->reference)->count());
    }

    public function test_invalid_signature_is_rejected_without_changing_payment(): void
    {
        Setting::setMany([
            'paystack_secret_key' => 'sk_test_webhook',
            'paystack_webhook_secret' => 'correct-secret',
        ], 'payments');
        [$invoice, $payment] = $this->pendingPayment();
        $raw = json_encode([
            'event' => 'charge.success',
            'data' => ['id' => 700, 'reference' => $payment->reference],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/paystack', [], [], [], [
            'HTTP_HOST' => config('platform.hosts.public'),
            'HTTP_X_PAYSTACK_SIGNATURE' => 'invalid',
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertUnauthorized();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertSame(0, PaymentEvent::query()->count());
    }

    /**
     * @return array{FeeInvoice, Payment}
     */
    private function pendingPayment(): array
    {
        $user = User::factory()->role(UserRole::Student)->create([
            'email' => fake()->safeEmail(),
        ]);
        $student = Student::query()->create([
            'user_id' => $user->id,
            'admission_no' => 'ADM-26-WEBHOOK-'.fake()->unique()->numerify('###'),
            'status' => 'active',
        ]);
        $feeItem = FeeItem::query()->create([
            'name' => 'Webhook Fee '.fake()->unique()->numerify('###'),
            'amount' => 5000,
            'is_mandatory' => true,
        ]);
        $invoice = FeeInvoice::query()->create([
            'invoice_no' => 'INV-WEBHOOK-'.fake()->unique()->numerify('#####'),
            'student_id' => $student->id,
            'fee_item_id' => $feeItem->id,
            'amount_due' => 5000,
            'amount_paid' => 0,
            'balance' => 5000,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);
        $payment = Payment::query()->create([
            'fee_invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'provider' => PaymentProvider::Paystack,
            'reference' => 'PAYSTACK-WEBHOOK-'.fake()->unique()->numerify('######'),
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => PaymentStatus::Pending,
        ]);

        return [$invoice, $payment];
    }
}
