@php
    $themeService = app(\App\Services\Website\ThemeService::class);
    $theme = auth()->user()?->effectiveTheme() ?? $themeService->defaultMode();
    $themeTokens = $themeService->tokens($theme);
    $surfacePrefix = $surface === \App\Enums\PortalSurface::AppPortal ? 'app' : 'web';
    $settings = \App\Models\Setting::publicSettings();
    $schoolName = $settings['school_name'] ?? 'Beloved College';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme->value }}" style="{{ $themeService->cssVariables($themeTokens) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title') | {{ $schoolName }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-page" data-portal-surface="{{ $surface->value }}">
        <main class="auth-shell">
            <section class="auth-brand" aria-labelledby="brand-heading">
                <a class="brand-mark" href="{{ route($surfacePrefix.'.home') }}" aria-label="Return to portal home">BC</a>
                <div class="auth-brand-copy">
                    <p class="eyebrow">{{ $surface->label() }}</p>
                    <h1 id="brand-heading">{{ $schoolName }}</h1>
                    <p>Secure access to learning, records, payments and school administration through one connected Laravel platform.</p>
                </div>
                <p class="auth-brand-foot">Recovery, verification and shared session security across both portal surfaces.</p>
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
