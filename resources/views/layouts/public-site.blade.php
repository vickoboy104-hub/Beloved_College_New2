@php
    $themeService = app(\App\Services\Website\ThemeService::class);
    $themeMode = $themeMode ?? $themeService->defaultMode();
    $themeTokens = $themeTokens ?? $themeService->tokens($themeMode);
    $settings = $settings ?? \App\Models\Setting::publicSettings();
    $schoolName = $settings['school_name'] ?? 'Beloved College';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode->value }}" style="{{ $themeService->cssVariables($themeTokens) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', $schoolName)</title>
        <meta name="description" content="@yield('description', $settings['school_tagline'] ?? 'Purposeful learning, character and student development.')">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="public-site-page">
        <header class="public-site-header">
            <div class="public-header-inner">
                <a class="public-brand" href="{{ route('public.home') }}">
                    <span class="brand-mark">BC</span>
                    <span><strong>{{ $schoolName }}</strong><small>{{ $settings['school_tagline'] ?? 'Learning with purpose' }}</small></span>
                </a>

                <nav class="public-navigation" aria-label="Public website navigation">
                    <a @class(['is-active' => request()->routeIs('public.home')]) href="{{ route('public.home') }}">Home</a>
                    <a @class(['is-active' => request()->routeIs('public.about')]) href="{{ route('public.about') }}">About</a>
                    <a @class(['is-active' => request()->routeIs('public.admissions')]) href="{{ route('public.admissions') }}">Admissions</a>
                    <a @class(['is-active' => request()->routeIs('public.news.*')]) href="{{ route('public.news.index') }}">News</a>
                    <a @class(['is-active' => request()->routeIs('public.gallery')]) href="{{ route('public.gallery') }}">Gallery</a>
                    <a @class(['is-active' => request()->routeIs('public.contact*')]) href="{{ route('public.contact') }}">Contact</a>
                </nav>

                <div class="public-header-actions">
                    <a class="header-result-link" href="{{ route('public.result-checker.index') }}">Check Result</a>
                    <a class="header-portal-link" href="{{ route('app.login', ['audience' => 'student']) }}">Open Portal</a>
                </div>
            </div>
        </header>

        @if (session('status'))
            <div class="public-notice public-notice-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="public-notice public-notice-error" role="alert">
                <strong>Please review the information below.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <main>
            @yield('content')
        </main>

        <footer class="public-site-footer">
            <div class="public-footer-grid">
                <section>
                    <a class="public-brand footer-brand" href="{{ route('public.home') }}">
                        <span class="brand-mark">BC</span>
                        <span><strong>{{ $schoolName }}</strong><small>{{ $settings['school_tagline'] ?? 'Learning with purpose' }}</small></span>
                    </a>
                    <p>{{ $settings['school_address'] ?? 'Contact the school office for directions and visiting information.' }}</p>
                    <div class="footer-contact-links">
                        @if ($settings['school_phone'] ?? null)<a href="tel:{{ preg_replace('/\s+/', '', $settings['school_phone']) }}">{{ $settings['school_phone'] }}</a>@endif
                        @if ($settings['school_email'] ?? null)<a href="mailto:{{ $settings['school_email'] }}">{{ $settings['school_email'] }}</a>@endif
                        @if ($settings['school_whatsapp'] ?? null)<a href="https://wa.me/{{ preg_replace('/\D+/', '', $settings['school_whatsapp']) }}" rel="noopener">WhatsApp</a>@endif
                    </div>
                </section>

                <section>
                    <p class="eyebrow">Quick links</p>
                    <nav class="footer-navigation" aria-label="Footer navigation">
                        <a href="{{ route('public.admissions') }}">Admissions</a>
                        <a href="{{ route('public.news.index') }}">School news</a>
                        <a href="{{ route('public.result-checker.index') }}">Result checker</a>
                        <a href="{{ route('web.login', ['audience' => 'staff']) }}">Staff login</a>
                        <a href="{{ route('app.login', ['audience' => 'student']) }}">Student and Parent portal</a>
                    </nav>
                </section>

                <section>
                    <p class="eyebrow">School updates</p>
                    <h2>Receive important public announcements.</h2>
                    <form method="POST" action="{{ route('public.newsletter.subscribe') }}" class="newsletter-form">
                        @csrf
                        <label><span>Email address</span><input name="email" type="email" required></label>
                        <label class="newsletter-consent"><input name="consent" type="checkbox" value="1" required><span>I consent to receiving public school updates by email.</span></label>
                        <button type="submit">Subscribe</button>
                    </form>
                </section>
            </div>
            <div class="public-footer-bottom">
                <span>© {{ now()->year }} {{ $schoolName }}</span>
                <span>Classic and Dark accessibility-aware themes</span>
            </div>
        </footer>
    </body>
</html>
