@extends('layouts.auth')

@section('title', 'Verify Email')

@section('content')
    @php $surfacePrefix = $surface === \App\Enums\PortalSurface::AppPortal ? 'app' : 'web'; @endphp

    <header class="form-heading">
        <p class="eyebrow">Email verification</p>
        <h2>Verify your email address</h2>
        <p>A signed verification link will be sent to <strong>{{ $email }}</strong>. The link expires automatically.</p>
    </header>

    @if ($errors->any())
        <div class="notice notice-error" role="alert"><strong>Verification could not continue.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route($surfacePrefix.'.verification.send') }}" class="form-stack">
        @csrf
        <button class="primary-button" type="submit">Send verification link</button>
    </form>

    <div class="auth-inline-actions">
        <a class="secondary-link" href="{{ route($surfacePrefix.'.security.index') }}">Account security</a>
        <form method="POST" action="{{ route($surfacePrefix.'.logout') }}">@csrf<button class="text-button" type="submit">Sign out</button></form>
    </div>
@endsection
