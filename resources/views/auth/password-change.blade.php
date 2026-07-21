@extends('layouts.auth')

@section('title', 'Change Temporary Password')

@section('content')
    @php
        $surfacePrefix = $surface === \App\Enums\PortalSurface::AppPortal ? 'app' : 'web';
    @endphp

    <header class="form-heading">
        <p class="eyebrow">Account security</p>
        <h2>Create your private password</h2>
        <p>Your temporary password has completed its purpose. Replace it before opening any school workspace.</p>
    </header>

    @if ($errors->any())
        <div class="notice notice-error" role="alert">
            <strong>Password was not changed.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route($surfacePrefix.'.password-change.update') }}" class="form-stack">
        @csrf
        @method('PUT')

        <label class="field-group">
            <span>New password</span>
            <input
                name="password"
                type="password"
                autocomplete="new-password"
                minlength="8"
                required
                autofocus
                placeholder="At least 8 characters"
            >
        </label>

        <label class="field-group">
            <span>Confirm new password</span>
            <input
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                minlength="8"
                required
                placeholder="Repeat the new password"
            >
        </label>

        <button class="primary-button" type="submit">Save password and continue</button>
    </form>

    <form method="POST" action="{{ route($surfacePrefix.'.logout') }}" class="secondary-action-form">
        @csrf
        <button class="text-button" type="submit">Sign out instead</button>
    </form>
@endsection
