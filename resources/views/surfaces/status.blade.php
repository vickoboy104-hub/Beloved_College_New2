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
    <body data-portal-surface="{{ $portalSurface->value }}">
        <main class="mx-auto min-h-screen max-w-5xl px-6 py-16 sm:px-10 lg:py-24">
            <p class="text-sm font-semibold uppercase tracking-[0.2em]">
                {{ $portalSurface->label() }}
            </p>

            <h1 class="mt-5 max-w-3xl text-4xl font-bold tracking-tight sm:text-6xl">
                {{ $heading }}
            </h1>

            <p class="mt-6 max-w-2xl text-lg leading-8">
                {{ $summary }}
            </p>

            <dl class="mt-12 grid gap-x-10 gap-y-6 border-t pt-8 sm:grid-cols-3">
                <div>
                    <dt class="text-sm font-semibold">Backend</dt>
                    <dd class="mt-1">Laravel {{ app()->version() }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-semibold">Surface key</dt>
                    <dd class="mt-1">{{ $portalSurface->value }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-semibold">Theme contract</dt>
                    <dd class="mt-1">Classic and Dark</dd>
                </div>
            </dl>
        </main>
    </body>
</html>
