<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentProvider;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackGateway implements PaymentGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Paystack;
    }

    public function isConfigured(): bool
    {
        return filled(Setting::getValue('paystack_secret_key'));
    }

    public function initialize(object $invoice, Payment $payment): array
    {
        $secret = Setting::getValue('paystack_secret_key');
        $email = $this->customerEmail($invoice);

        if (! $secret) {
            throw new RuntimeException('Paystack secret key is not configured.');
        }

        $response = Http::withToken($secret)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 300)
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $email,
                'amount' => (int) round(((float) $payment->amount) * 100),
                'currency' => $payment->currency,
                'reference' => $payment->reference,
                'callback_url' => route('public.payments.callback', 'paystack'),
                'metadata' => [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id ?? null,
                    'invoice_ids' => data_get($payment->payload, 'invoice_ids', []),
                    'student_id' => $payment->student_id,
                ],
            ]);

        if ($response->failed() || blank($response->json('data.authorization_url'))) {
            throw new RuntimeException($response->json('message') ?: 'Unable to initialize Paystack payment.');
        }

        return [
            'status' => true,
            'message' => $response->json('message'),
            'data' => [
                'authorization_url' => $response->json('data.authorization_url'),
                'reference' => $response->json('data.reference') ?: $payment->reference,
                'gateway_reference' => $response->json('data.access_code'),
            ],
            'raw' => $response->json(),
        ];
    }

    public function verify(string $reference, array $context = []): array
    {
        $secret = Setting::getValue('paystack_secret_key');

        if (! $secret) {
            throw new RuntimeException('Paystack secret key is not configured.');
        }

        $response = Http::withToken($secret)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 300)
            ->get('https://api.paystack.co/transaction/verify/'.rawurlencode($reference));

        if ($response->failed()) {
            throw new RuntimeException($response->json('message') ?: 'Unable to verify Paystack payment.');
        }

        $data = $response->json('data', []);

        return [
            'status' => true,
            'data' => [
                'status' => $data['status'] ?? null,
                'reference' => $data['reference'] ?? $reference,
                'gateway_reference' => $data['id'] ?? $data['reference'] ?? null,
                'amount' => $data['amount'] ?? null,
                'currency' => $data['currency'] ?? null,
                'channel' => $data['channel'] ?? null,
                'paid_at' => $data['paid_at'] ?? null,
            ],
            'raw' => $response->json(),
        ];
    }

    private function customerEmail(object $invoice): string
    {
        $email = $invoice->student?->user?->email
            ?: $invoice->student?->parent?->email
            ?: Setting::getValue('finance_fallback_email');

        if (! $email) {
            throw new RuntimeException('A student, parent, or finance fallback email is required for online payment.');
        }

        return (string) $email;
    }
}
