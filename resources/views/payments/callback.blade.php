@php
    $themeService = app(\App\Services\Website\ThemeService::class);
    $themeMode = $themeService->defaultMode();
    $themeTokens = $themeService->tokens($themeMode);
    $settings = \App\Models\Setting::publicSettings();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode->value }}" style="{{ $themeService->cssVariables($themeTokens) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Payment Status | {{ $settings['school_name'] ?? 'Beloved College' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="payment-callback-page">
        <main class="payment-callback-shell">
            <a class="portal-brand" href="{{ route('public.home') }}"><span class="brand-mark">BC</span><span><strong>{{ $settings['school_name'] ?? 'Beloved College' }}</strong><small>Payment verification</small></span></a>

            <section class="callback-status callback-status-{{ $status }}">
                <p class="eyebrow">{{ $provider->label() }}</p>
                @if ($status === 'paid')
                    <h1>Payment verified</h1>
                    <p>The provider confirmed the payment and the school balance has been updated.</p>
                @elseif ($status === 'failed')
                    <h1>Payment not verified</h1>
                    <p>The returned payment details did not match the expected reference, currency, amount, or successful status. No value was recorded.</p>
                @else
                    <h1>Verification is pending</h1>
                    <p>The provider verification service could not be reached. The school has not marked this payment as paid. A signed webhook may complete it later.</p>
                @endif

                @if ($payment)
                    <dl class="callback-facts">
                        <div><dt>Reference</dt><dd>{{ $payment->reference }}</dd></div>
                        <div><dt>Amount</dt><dd>₦{{ number_format((float) $payment->amount, 2) }}</dd></div>
                        <div><dt>Recorded status</dt><dd>{{ $payment->status->label() }}</dd></div>
                    </dl>
                @endif

                <div class="callback-actions">
                    <a class="primary-link" href="{{ route('app.login', ['audience' => 'student']) }}">Open mobile portal</a>
                    <a class="secondary-link" href="{{ route('web.login') }}">Open web portal</a>
                </div>
            </section>
        </main>
    </body>
</html>
