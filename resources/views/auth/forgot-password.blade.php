@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
    @php $surfacePrefix = $surface === \App\Enums\PortalSurface::AppPortal ? 'app' : 'web'; @endphp

    <header class="form-heading">
        <p class="eyebrow">Account recovery</p>
        <h2>Reset your password</h2>
        <p>Enter the email address assigned to your account. Admission numbers, student IDs and employee numbers cannot receive email links.</p>
    </header>

    @if ($errors->any())
        <div class="notice notice-error" role="alert"><strong>Recovery was not completed.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route($surfacePrefix.'.password.email') }}" class="form-stack">
        @csrf
        <label class="field-group"><span>Email address</span><input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
        <button class="primary-button" type="submit">Send reset link</button>
    </form>

    <p class="form-help">For privacy, the same confirmation is shown whether or not an account matches the email.</p>
    <a class="auth-back-link" href="{{ route($surfacePrefix.'.login') }}">Back to sign in</a>
@endsection
