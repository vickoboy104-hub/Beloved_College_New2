@extends('layouts.portal')

@section('title', 'Payment Gateways')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">Payment infrastructure</p>
            <h1>Gateway Settings</h1>
            <p>Enable only fully configured providers. Secret values are encrypted at rest and blank fields preserve existing secrets.</p>
        </div>
        <a class="secondary-link" href="{{ route('web.admin.finance.index') }}">Back to Finance</a>
    </header>

    <section class="gateway-status-grid">
        @foreach ($gateways as $gateway)
            <article>
                <div><strong>{{ $gateway['label'] }}</strong><span>{{ $gateway['available'] ? 'Available' : ($gateway['configured'] ? 'Configured but disabled' : 'Not configured') }}</span></div>
                <span class="status-badge status-{{ $gateway['available'] ? 'active' : 'inactive' }}">{{ $gateway['available'] ? 'Live in portal' : 'Unavailable' }}</span>
                <dl><div><dt>Callback</dt><dd><code>{{ $gateway['callback_url'] }}</code></dd></div><div><dt>Webhook</dt><dd><code>{{ $gateway['webhook_url'] }}</code></dd></div></dl>
            </article>
        @endforeach
    </section>

    <form method="POST" action="{{ route('web.admin.finance.gateways.update') }}" class="long-form gateway-settings-form">
        @csrf
        @method('PUT')

        <fieldset class="form-section">
            <legend>Enabled providers and fallback contact</legend>
            <div class="check-grid gateway-enable-grid">
                @foreach (['paystack' => 'Paystack', 'flutterwave' => 'Flutterwave', 'monnify' => 'Monnify', 'palmpay' => 'PalmPay'] as $value => $label)
                    <label class="check-row"><input name="enabled_payment_gateways[]" type="checkbox" value="{{ $value }}" @checked(in_array($value, $enabledValues, true))><span>{{ $label }}</span></label>
                @endforeach
            </div>
            <label class="field-group gateway-fallback-field"><span>Fallback customer email</span><input name="finance_fallback_email" type="email" value="{{ old('finance_fallback_email', $settings['finance_fallback_email'] ?? '') }}"><small>Used only when neither the student nor linked parent has an email required by the provider.</small></label>
        </fieldset>

        <fieldset class="form-section">
            <legend>Paystack</legend>
            <div class="form-grid form-grid-3">
                <label class="field-group"><span>Public key</span><input name="paystack_public_key" value="{{ old('paystack_public_key', $settings['paystack_public_key'] ?? '') }}"></label>
                <label class="field-group"><span>Secret key</span><input name="paystack_secret_key" type="password" autocomplete="new-password" placeholder="Leave blank to preserve"></label>
                <label class="field-group"><span>Webhook secret override</span><input name="paystack_webhook_secret" type="password" autocomplete="new-password" placeholder="Defaults to secret key"></label>
            </div>
        </fieldset>

        <fieldset class="form-section">
            <legend>Flutterwave</legend>
            <div class="form-grid form-grid-3">
                <label class="field-group"><span>Public key</span><input name="flutterwave_public_key" value="{{ old('flutterwave_public_key', $settings['flutterwave_public_key'] ?? '') }}"></label>
                <label class="field-group"><span>Secret key</span><input name="flutterwave_secret_key" type="password" autocomplete="new-password" placeholder="Leave blank to preserve"></label>
                <label class="field-group"><span>Webhook secret hash</span><input name="flutterwave_secret_hash" type="password" autocomplete="new-password" placeholder="Leave blank to preserve"></label>
                <label class="field-group form-span-full"><span>Payment options</span><input name="flutterwave_payment_options" value="{{ old('flutterwave_payment_options', $settings['flutterwave_payment_options'] ?? 'card,banktransfer,ussd') }}"></label>
            </div>
        </fieldset>

        <fieldset class="form-section">
            <legend>Monnify</legend>
            <div class="form-grid form-grid-3">
                <label class="field-group"><span>API key</span><input name="monnify_api_key" value="{{ old('monnify_api_key', $settings['monnify_api_key'] ?? '') }}"></label>
                <label class="field-group"><span>Secret key</span><input name="monnify_secret_key" type="password" autocomplete="new-password" placeholder="Leave blank to preserve"></label>
                <label class="field-group"><span>Contract code</span><input name="monnify_contract_code" value="{{ old('monnify_contract_code', $settings['monnify_contract_code'] ?? '') }}"></label>
                <label class="field-group"><span>Environment</span><select name="monnify_environment"><option value="sandbox" @selected(($settings['monnify_environment'] ?? 'sandbox') === 'sandbox')>Sandbox</option><option value="live" @selected(($settings['monnify_environment'] ?? '') === 'live')>Live</option></select></label>
                <label class="field-group form-span-2"><span>Payment methods</span><input name="monnify_payment_methods" value="{{ old('monnify_payment_methods', $settings['monnify_payment_methods'] ?? 'CARD,ACCOUNT_TRANSFER,USSD') }}"></label>
            </div>
        </fieldset>

        <fieldset class="form-section palm-pay-warning">
            <legend>PalmPay merchant details</legend>
            <div class="notice notice-error">PalmPay automatic settlement remains unavailable until a merchant-specific server verification and signature contract is supplied. Saving these fields does not make it available to families.</div>
            <div class="form-grid form-grid-3">
                <label class="field-group"><span>Merchant ID</span><input name="palmpay_merchant_id" value="{{ old('palmpay_merchant_id', $settings['palmpay_merchant_id'] ?? '') }}"></label>
                <label class="field-group"><span>App ID</span><input name="palmpay_app_id" value="{{ old('palmpay_app_id', $settings['palmpay_app_id'] ?? '') }}"></label>
                <label class="field-group"><span>Checkout URL</span><input name="palmpay_checkout_url" type="url" value="{{ old('palmpay_checkout_url', $settings['palmpay_checkout_url'] ?? '') }}"></label>
                <label class="field-group"><span>Private key</span><textarea name="palmpay_private_key" rows="4" placeholder="Leave blank to preserve"></textarea></label>
                <label class="field-group"><span>Webhook secret</span><input name="palmpay_webhook_secret" type="password" autocomplete="new-password" placeholder="Leave blank to preserve"></label>
            </div>
        </fieldset>

        <div class="form-actions"><button class="primary-button" type="submit">Save encrypted gateway settings</button></div>
    </form>
@endsection
