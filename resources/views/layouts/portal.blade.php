@php
    $theme = auth()->user()->effectiveTheme();
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
    <body class="portal-page" data-portal-surface="{{ $surface->value }}">
        <div class="portal-shell">
            <aside class="portal-sidebar">
                <a class="portal-brand" href="{{ route($surfacePrefix.'.dashboard') }}">
                    <span class="brand-mark">BC</span>
                    <span>
                        <strong>Beloved College</strong>
                        <small>{{ $surface->label() }}</small>
                    </span>
                </a>

                <nav class="portal-navigation" aria-label="Primary navigation">
                    <a class="is-active" href="{{ route($surfacePrefix.'.dashboard') }}" aria-current="page">Dashboard</a>
                </nav>

                <div class="portal-user-summary">
                    <strong>{{ auth()->user()->fullName() }}</strong>
                    <span>{{ auth()->user()->roleLabel() }}</span>
                    <form method="POST" action="{{ route($surfacePrefix.'.logout') }}">
                        @csrf
                        <button class="text-button" type="submit">Sign out</button>
                    </form>
                </div>
            </aside>

            <div class="portal-workspace">
                <header class="portal-topbar">
                    <div>
                        <p class="eyebrow">{{ auth()->user()->roleLabel() }}</p>
                        <strong>{{ $surface->label() }}</strong>
                    </div>
                    <span class="theme-indicator">{{ $theme->label() }} theme</span>
                </header>

                <main class="portal-main">
                    @if (session('status'))
                        <div class="notice notice-success" role="status">{{ session('status') }}</div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
