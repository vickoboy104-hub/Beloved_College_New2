@extends('layouts.auth')

@section('title', 'Reset Password')

@section('content')
    @php $surfacePrefix = $surface === \App\Enums\PortalSurface::AppPortal ? 'app' : 'web'; @endphp

    <header class="form-heading">
        <p class="eyebrow">Account recovery</p>
        <h2>Create a new password</h2>
        <p>Use at least 10 characters with uppercase and lowercase letters and a number. Completing the reset signs out every existing session.</p>
    </header>

    @if ($errors->any())
        <div class="notice notice-error" role="alert"><strong>Password reset was not completed.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route($surfacePrefix.'.password.update') }}" class="form-stack">
        @csrf
        <input name="token" type="hidden" value="{{ $token }}">
        <label class="field-group"><span>Email address</span><input name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required></label>
        <label class="field-group"><span>New password</span><input name="password" type="password" autocomplete="new-password" required></label>
        <label class="field-group"><span>Confirm new password</span><input name="password_confirmation" type="password" autocomplete="new-password" required></label>
        <button class="primary-button" type="submit">Reset password</button>
    </form>

    <a class="auth-back-link" href="{{ route($surfacePrefix.'.login') }}">Back to sign in</a>
@endsection
