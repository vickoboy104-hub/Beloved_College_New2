<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentProvider;
use App\Models\Payment;
use RuntimeException;

class PalmPayGateway implements PaymentGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::PalmPay;
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function initialize(object $invoice, Payment $payment): array
    {
        throw new RuntimeException(
            'PalmPay automatic checkout is unavailable until the school provides its merchant-specific server verification contract.',
        );
    }

    public function verify(string $reference, array $context = []): array
    {
        throw new RuntimeException(
            'PalmPay server verification is not configured. No payment was marked as paid.',
        );
    }
}
