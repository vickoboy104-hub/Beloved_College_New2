<?php

namespace App\Services\Payments;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\FeeInvoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentCheckoutService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentAccessService $access,
        private readonly PaymentSettlementService $settlement,
    ) {}

    /**
     * @return array{payment: Payment, authorization_url: string}
     */
    public function startInvoice(User $actor, FeeInvoice $invoice, PaymentProvider $provider): array
    {
        $invoice->loadMissing('student.user', 'student.parent', 'feeItem');
        $this->access->authorizeInvoice($actor, $invoice);
        $this->assertProviderAvailable($provider);

        if ((float) $invoice->balance <= 0) {
            throw ValidationException::withMessages([
                'payment' => 'This invoice has already been settled.',
            ]);
        }

        $payment = Payment::query()->create([
            'fee_invoice_id' => $invoice->id,
            'student_id' => $invoice->student_id,
            'provider' => $provider,
            'reference' => $this->reference($provider),
            'amount' => $invoice->balance,
            'currency' => 'NGN',
            'status' => PaymentStatus::Initialized,
            'payload' => [
                'source' => 'single_invoice_checkout',
                'invoice_ids' => [$invoice->id],
                'initiated_by' => $actor->id,
            ],
        ]);

        return $this->initialize($invoice, $payment, $provider);
    }

    /**
     * @param  array<int, int>  $invoiceIds
     * @return array{payment: Payment, authorization_url: string}
     */
    public function startBundle(User $actor, array $invoiceIds, PaymentProvider $provider): array
    {
        $this->assertProviderAvailable($provider);
        $invoices = FeeInvoice::query()
            ->with('student.user', 'student.parent', 'feeItem')
            ->whereIn('id', collect($invoiceIds)->map(fn (mixed $id) => (int) $id)->unique())
            ->get()
            ->filter(fn (FeeInvoice $invoice) => (float) $invoice->balance > 0)
            ->values();

        if ($invoices->isEmpty()) {
            throw ValidationException::withMessages([
                'invoice_ids' => 'Select at least one unpaid fee item.',
            ]);
        }

        if ($invoices->pluck('student_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages([
                'invoice_ids' => 'Selected fee items must belong to the same student.',
            ]);
        }

        foreach ($invoices as $invoice) {
            $this->access->authorizeInvoice($actor, $invoice);
        }

        $primaryInvoice = $invoices->first();
        $payment = Payment::query()->create([
            'fee_invoice_id' => null,
            'student_id' => $primaryInvoice->student_id,
            'provider' => $provider,
            'reference' => $this->reference($provider),
            'amount' => $invoices->sum(fn (FeeInvoice $invoice) => (float) $invoice->balance),
            'currency' => 'NGN',
            'status' => PaymentStatus::Initialized,
            'payload' => [
                'source' => 'bundle_checkout',
                'invoice_ids' => $invoices->pluck('id')->values()->all(),
                'bundle_label' => 'Selected fee items',
                'initiated_by' => $actor->id,
            ],
        ]);
        $bundleSubject = (object) [
            'id' => null,
            'invoice_no' => 'BUNDLE-'.$primaryInvoice->student->admission_no,
            'student_id' => $primaryInvoice->student_id,
            'student' => $primaryInvoice->student,
        ];

        return $this->initialize($bundleSubject, $payment, $provider);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function confirm(PaymentProvider $provider, string $reference, array $context = []): Payment
    {
        $payment = Payment::query()
            ->with('feeInvoice')
            ->where('reference', $reference)
            ->where('provider', $provider->value)
            ->firstOrFail();

        if ($payment->status === PaymentStatus::Paid) {
            return $payment;
        }

        $verification = $this->gateways->gateway($provider)->verify($reference, $context);

        if (! $this->settlement->matches($payment, $provider, $verification)) {
            return $this->settlement->fail(
                $payment,
                'Gateway verification did not match the expected reference, currency, status, or amount.',
                ['verification' => $verification],
            );
        }

        return $this->settlement->settle($payment, [
            'gateway_reference' => data_get($verification, 'data.gateway_reference'),
            'channel' => data_get($verification, 'data.channel'),
            'paid_at' => data_get($verification, 'data.paid_at') ?: now(),
            'payload' => ['verification' => $verification],
        ]);
    }

    /**
     * @return array{payment: Payment, authorization_url: string}
     */
    private function initialize(object $invoice, Payment $payment, PaymentProvider $provider): array
    {
        try {
            $response = $this->gateways->gateway($provider)->initialize($invoice, $payment);
            $authorizationUrl = (string) data_get($response, 'data.authorization_url');

            if ($authorizationUrl === '') {
                throw new RuntimeException('The payment provider did not return a checkout URL.');
            }

            $payment->update([
                'status' => PaymentStatus::Pending,
                'gateway_reference' => data_get($response, 'data.gateway_reference'),
                'payload' => array_merge($payment->payload ?? [], [
                    'gateway_initialization' => $response,
                ]),
            ]);

            return [
                'payment' => $payment->fresh(),
                'authorization_url' => $authorizationUrl,
            ];
        } catch (\Throwable $exception) {
            report($exception);
            $this->settlement->fail($payment, 'Payment initialization failed.');

            throw ValidationException::withMessages([
                'payment' => $provider->label().' could not start the payment. Check the gateway configuration or try another enabled method.',
            ]);
        }
    }

    private function assertProviderAvailable(PaymentProvider $provider): void
    {
        if (! $provider->isOnline() || ! $this->gateways->isAvailable($provider)) {
            throw ValidationException::withMessages([
                'payment' => $provider->label().' is disabled or not completely configured by the school.',
            ]);
        }
    }

    private function reference(PaymentProvider $provider): string
    {
        do {
            $reference = Str::upper($provider->value).'-'.Str::upper(Str::random(14));
        } while (Payment::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
