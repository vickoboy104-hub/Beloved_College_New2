@php
    $theme = \App\Enums\ThemeMode::default();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme->value }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $heading }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="surface-page" data-portal-surface="{{ $portalSurface->value }}">
        <main class="surface-main">
            <p class="eyebrow">{{ $portalSurface->label() }}</p>
            <h1>{{ $heading }}</h1>
            <p class="surface-summary">{{ $summary }}</p>

            <div class="surface-actions">
                <a class="primary-link" href="{{ route('web.login', ['audience' => 'staff']) }}">Staff web login</a>
                <a class="secondary-link" href="{{ route('app.login', ['audience' => 'student']) }}">Student mobile login</a>
            </div>

            <dl class="surface-facts">
                <div>
                    <dt>Backend</dt>
                    <dd>Laravel {{ app()->version() }}</dd>
                </div>
                <div>
                    <dt>Surface key</dt>
                    <dd>{{ $portalSurface->value }}</dd>
                </div>
                <div>
                    <dt>Theme contract</dt>
                    <dd>Classic and Dark</dd>
                </div>
            </dl>
        </main>
    </body>
</html>
