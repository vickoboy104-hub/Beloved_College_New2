<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentProvider;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MonnifyGateway implements PaymentGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Monnify;
    }

    public function isConfigured(): bool
    {
        return filled(Setting::getValue('monnify_api_key'))
            && filled(Setting::getValue('monnify_secret_key'))
            && filled(Setting::getValue('monnify_contract_code'));
    }

    public function initialize(object $invoice, Payment $payment): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Monnify API key, secret key, and contract code are required.');
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 300)
            ->post($this->baseUrl().'/api/v1/merchant/transactions/init-transaction', [
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'customerName' => $invoice->student?->user?->fullName(),
                'customerEmail' => $this->customerEmail($invoice),
                'paymentReference' => $payment->reference,
                'paymentDescription' => 'School fee payment for '.($invoice->invoice_no ?? $payment->reference),
                'currencyCode' => $payment->currency,
                'contractCode' => Setting::getValue('monnify_contract_code'),
                'redirectUrl' => route('public.payments.callback', 'monnify'),
                'paymentMethods' => $this->paymentMethods(),
                'metaData' => [
                    'payment_id' => $payment->id,
                    'student_id' => $payment->student_id,
                    'invoice_ids' => data_get($payment->payload, 'invoice_ids', []),
                ],
            ]);

        $checkoutUrl = $response->json('responseBody.checkoutUrl');

        if ($response->failed() || blank($checkoutUrl)) {
            throw new RuntimeException($response->json('responseMessage') ?: 'Unable to initialize Monnify payment.');
        }

        return [
            'status' => true,
            'message' => $response->json('responseMessage'),
            'data' => [
                'authorization_url' => $checkoutUrl,
                'reference' => $payment->reference,
                'gateway_reference' => $response->json('responseBody.transactionReference'),
            ],
            'raw' => $response->json(),
        ];
    }

    public function verify(string $reference, array $context = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Monnify is not configured.');
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 300)
            ->get($this->baseUrl().'/api/v2/transactions/'.rawurlencode($reference));

        if ($response->failed()) {
            throw new RuntimeException($response->json('responseMessage') ?: 'Unable to verify Monnify payment.');
        }

        $body = $response->json('responseBody', []);

        return [
            'status' => true,
            'data' => [
                'status' => $body['paymentStatus'] ?? null,
                'reference' => $body['paymentReference'] ?? $reference,
                'gateway_reference' => $body['transactionReference'] ?? null,
                'amount' => $body['amountPaid'] ?? $body['amount'] ?? null,
                'currency' => $body['currencyCode'] ?? null,
                'channel' => $body['paymentMethod'] ?? null,
                'paid_at' => $body['paidOn'] ?? $body['completedOn'] ?? null,
            ],
            'raw' => $response->json(),
        ];
    }

    private function accessToken(): string
    {
        $cacheKey = 'payments.monnify.token.'.sha1($this->baseUrl().'|'.Setting::getValue('monnify_api_key'));

        return Cache::remember($cacheKey, now()->addMinutes(50), function (): string {
            $credentials = base64_encode(
                Setting::getValue('monnify_api_key').':'.Setting::getValue('monnify_secret_key'),
            );
            $response = Http::withHeaders([
                'Authorization' => 'Basic '.$credentials,
            ])->acceptJson()
                ->timeout(30)
                ->retry(2, 300)
                ->post($this->baseUrl().'/api/v1/auth/login');
            $token = $response->json('responseBody.accessToken');

            if ($response->failed() || blank($token)) {
                throw new RuntimeException($response->json('responseMessage') ?: 'Unable to authenticate with Monnify.');
            }

            return (string) $token;
        });
    }

    private function baseUrl(): string
    {
        return Setting::getValue('monnify_environment', 'sandbox') === 'live'
            ? 'https://api.monnify.com'
            : 'https://sandbox.monnify.com';
    }

    /**
     * @return array<int, string>
     */
    private function paymentMethods(): array
    {
        $configured = array_filter(array_map(
            'trim',
            explode(',', (string) Setting::getValue('monnify_payment_methods', 'CARD,ACCOUNT_TRANSFER,USSD')),
        ));

        return $configured ?: ['CARD', 'ACCOUNT_TRANSFER', 'USSD'];
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
