@php
    $theme = auth()->user()?->effectiveTheme() ?? \App\Enums\ThemeMode::default();
    $surfacePrefix = $surface === \App\Enums\PortalSurface::AppPortal ? 'app' : 'web';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme->value }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title') | {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-page" data-portal-surface="{{ $surface->value }}">
        <main class="auth-shell">
            <section class="auth-brand" aria-labelledby="brand-heading">
                <a class="brand-mark" href="{{ route($surfacePrefix.'.home') }}" aria-label="Return to portal home">BC</a>
                <div class="auth-brand-copy">
                    <p class="eyebrow">{{ $surface->label() }}</p>
                    <h1 id="brand-heading">Beloved College</h1>
                    <p>Secure access to learning, records, payments and school administration through one connected Laravel platform.</p>
                </div>
                <p class="auth-brand-foot">Classic and Dark themes. One database. One permission system.</p>
            </section>

            <section class="auth-form-panel">
                <div class="auth-form-width">
                    @if (session('status'))
                        <div class="notice notice-success" role="status">{{ session('status') }}</div>
                    @endif

                    @yield('content')
                </div>
            </section>
        </main>
    </body>
</html>
