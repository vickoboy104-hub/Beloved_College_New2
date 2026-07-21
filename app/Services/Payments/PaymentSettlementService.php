<?php

namespace App\Services\Payments;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentSettlementService
{
    /**
     * @param  array<string, mixed>  $verification
     */
    public function matches(Payment $payment, PaymentProvider $provider, array $verification): bool
    {
        $status = strtolower((string) data_get($verification, 'data.status'));
        $reference = (string) data_get($verification, 'data.reference');
        $currency = strtoupper((string) data_get($verification, 'data.currency'));
        $amount = data_get($verification, 'data.amount');

        if (! hash_equals($payment->reference, $reference)
            || $currency !== strtoupper((string) $payment->currency)) {
            return false;
        }

        return match ($provider) {
            PaymentProvider::Paystack => $status === 'success'
                && (int) $amount === (int) round(((float) $payment->amount) * 100),
            PaymentProvider::Flutterwave => in_array($status, ['successful', 'success'], true)
                && (float) $amount + 0.00001 >= (float) $payment->amount,
            PaymentProvider::Monnify => in_array($status, ['paid', 'overpaid'], true)
                && (float) $amount + 0.00001 >= (float) $payment->amount,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function settle(Payment $payment, array $attributes = []): Payment
    {
        return DB::transaction(function () use ($payment, $attributes): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === PaymentStatus::Paid) {
                return $locked->fresh();
            }

            $locked->update([
                'gateway_reference' => $attributes['gateway_reference'] ?? $locked->gateway_reference,
                'status' => PaymentStatus::Paid,
                'channel' => $attributes['channel'] ?? $locked->channel,
                'paid_at' => $attributes['paid_at'] ?? now(),
                'receipt_no' => $locked->receipt_no ?: $this->receiptNumber(),
                'payload' => array_merge($locked->payload ?? [], $attributes['payload'] ?? []),
            ]);

            $locked->refresh()->loadMissing('feeInvoice');

            if ($locked->feeInvoice) {
                $locked->feeInvoice->syncBalance();
            } else {
                $locked->allocateBundleInvoices();
            }

            return $locked->fresh(['feeInvoice', 'student']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function fail(Payment $payment, string $message, array $payload = []): Payment
    {
        if ($payment->status === PaymentStatus::Paid) {
            return $payment;
        }

        $payment->update([
            'status' => PaymentStatus::Failed,
            'payload' => array_merge($payment->payload ?? [], $payload, [
                'verification_message' => $message,
            ]),
        ]);

        return $payment->fresh();
    }

    private function receiptNumber(): string
    {
        do {
            $number = 'RCP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Payment::query()->where('receipt_no', $number)->exists());

        return $number;
    }
}
