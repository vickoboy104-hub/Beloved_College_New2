<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentProvider;
use App\Models\Setting;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $gateways;

    public function __construct(
        PaystackGateway $paystack,
        FlutterwaveGateway $flutterwave,
        MonnifyGateway $monnify,
        PalmPayGateway $palmPay,
    ) {
        $this->gateways = collect([$paystack, $flutterwave, $monnify, $palmPay])
            ->mapWithKeys(fn (PaymentGateway $gateway) => [$gateway->provider()->value => $gateway])
            ->all();
    }

    public function gateway(PaymentProvider|string $provider): PaymentGateway
    {
        $value = $provider instanceof PaymentProvider ? $provider->value : $provider;
        $gateway = $this->gateways[$value] ?? null;

        if (! $gateway) {
            throw new InvalidArgumentException('Unsupported online payment gateway: '.$value);
        }

        return $gateway;
    }

    public function isEnabled(PaymentProvider|string $provider): bool
    {
        $value = $provider instanceof PaymentProvider ? $provider->value : $provider;

        return in_array($value, $this->enabledValues(), true);
    }

    public function isAvailable(PaymentProvider|string $provider): bool
    {
        $value = $provider instanceof PaymentProvider ? $provider->value : $provider;

        return $this->isEnabled($value) && $this->gateway($value)->isConfigured();
    }

    /**
     * @return array<int, string>
     */
    public function enabledValues(): array
    {
        $raw = Setting::getValue('enabled_payment_gateways', 'paystack,flutterwave,monnify');
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);

        return collect($values)
            ->map(fn (mixed $value) => strtolower(trim((string) $value)))
            ->filter(fn (string $value) => array_key_exists($value, $this->gateways))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function catalog(bool $onlyAvailable = false): Collection
    {
        return collect($this->gateways)
            ->map(function (PaymentGateway $gateway): array {
                $provider = $gateway->provider();

                return [
                    'value' => $provider->value,
                    'label' => $provider->label(),
                    'enabled' => $this->isEnabled($provider),
                    'configured' => $gateway->isConfigured(),
                    'available' => $this->isAvailable($provider),
                    'callback_url' => route('public.payments.callback', $provider->value),
                    'webhook_url' => route('public.webhooks.'.$provider->value),
                ];
            })
            ->when($onlyAvailable, fn (Collection $items) => $items->where('available', true))
            ->values();
    }
}
