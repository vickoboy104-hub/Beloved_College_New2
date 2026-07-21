@extends('layouts.auth')

@section('title', $audience->label().' Login')

@section('content')
    @php
        $surfacePrefix = $surface === \App\Enums\PortalSurface::AppPortal ? 'app' : 'web';
    @endphp

    <header class="form-heading">
        <p class="eyebrow">{{ $audience->label() }} access</p>
        <h2>Sign in to your account</h2>
        <p>
            Use your email or account name. Students may also use an admission number or student ID, while staff may use an employee number.
        </p>
    </header>

    <nav class="audience-switcher" aria-label="Choose login type">
        @foreach (\App\Enums\LoginAudience::cases() as $option)
            <a
                href="{{ route($surfacePrefix.'.login', ['audience' => $option->value]) }}"
                @class(['is-active' => $audience === $option])
                @if ($audience === $option) aria-current="page" @endif
            >{{ $option->label() }}</a>
        @endforeach
    </nav>

    @if ($errors->any())
        <div class="notice notice-error" role="alert">
            <strong>Sign-in was not completed.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route($surfacePrefix.'.login.store') }}" class="form-stack">
        @csrf
        <input type="hidden" name="audience" value="{{ $audience->value }}">

        <label class="field-group">
            <span>Login identifier</span>
            <input
                name="login"
                type="text"
                value="{{ old('login') }}"
                autocomplete="username"
                inputmode="email"
                required
                autofocus
                placeholder="Email, admission number or employee number"
            >
        </label>

        <label class="field-group">
            <span>Password</span>
            <input
                name="password"
                type="password"
                autocomplete="current-password"
                required
                placeholder="Enter your password"
            >
        </label>

        <label class="check-row">
            <input name="remember" type="checkbox" value="1">
            <span>Keep me signed in on this device</span>
        </label>

        <button class="primary-button" type="submit">Sign in</button>
    </form>

    <p class="form-help">
        Account access is issued by the school. Contact the school office when you cannot recover your login details.
    </p>
@endsection
