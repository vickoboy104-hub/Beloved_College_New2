<?php

namespace App\Contracts;

use App\Enums\PaymentProvider;
use App\Models\Payment;

interface PaymentGateway
{
    public function provider(): PaymentProvider;

    public function isConfigured(): bool;

    /**
     * @return array<string, mixed>
     */
    public function initialize(object $invoice, Payment $payment): array;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function verify(string $reference, array $context = []): array;
}
