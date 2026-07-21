<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentProvider;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlutterwaveGateway implements PaymentGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Flutterwave;
    }

    public function isConfigured(): bool
    {
        return filled(Setting::getValue('flutterwave_secret_key'));
    }

    public function initialize(object $invoice, Payment $payment): array
    {
        $secret = Setting::getValue('flutterwave_secret_key');

        if (! $secret) {
            throw new RuntimeException('Flutterwave secret key is not configured.');
        }

        $response = Http::withToken($secret)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 300)
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $payment->reference,
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => $payment->currency,
                'redirect_url' => route('public.payments.callback', 'flutterwave'),
                'payment_options' => Setting::getValue('flutterwave_payment_options', 'card,banktransfer,ussd'),
                'customer' => [
                    'email' => $this->customerEmail($invoice),
                    'phonenumber' => $invoice->student?->user?->phone,
                    'name' => $invoice->student?->user?->fullName(),
                ],
                'customizations' => [
                    'title' => Setting::getValue('school_name', config('app.name')),
                    'description' => 'School fee payment for '.($invoice->invoice_no ?? $payment->reference),
                ],
                'meta' => [
                    'payment_id' => $payment->id,
                    'student_id' => $payment->student_id,
                    'invoice_ids' => data_get($payment->payload, 'invoice_ids', []),
                ],
            ]);

        if ($response->failed() || blank($response->json('data.link'))) {
            throw new RuntimeException($response->json('message') ?: 'Unable to initialize Flutterwave payment.');
        }

        return [
            'status' => true,
            'message' => $response->json('message'),
            'data' => [
                'authorization_url' => $response->json('data.link'),
                'reference' => $payment->reference,
            ],
            'raw' => $response->json(),
        ];
    }

    public function verify(string $reference, array $context = []): array
    {
        $secret = Setting::getValue('flutterwave_secret_key');
        $transactionId = $context['transaction_id'] ?? null;

        if (! $secret) {
            throw new RuntimeException('Flutterwave secret key is not configured.');
        }

        if (! $transactionId) {
            throw new RuntimeException('Flutterwave transaction ID is required for verification.');
        }

        $response = Http::withToken($secret)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 300)
            ->get('https://api.flutterwave.com/v3/transactions/'.rawurlencode((string) $transactionId).'/verify');

        if ($response->failed()) {
            throw new RuntimeException($response->json('message') ?: 'Unable to verify Flutterwave payment.');
        }

        $data = $response->json('data', []);

        return [
            'status' => true,
            'data' => [
                'status' => $data['status'] ?? null,
                'reference' => $data['tx_ref'] ?? $reference,
                'gateway_reference' => $data['flw_ref'] ?? $transactionId,
                'amount' => $data['amount'] ?? null,
                'currency' => $data['currency'] ?? null,
                'channel' => $data['payment_type'] ?? null,
                'paid_at' => $data['created_at'] ?? null,
                'transaction_id' => $data['id'] ?? $transactionId,
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
