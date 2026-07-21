<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode->value }}" style="{{ app(\App\Services\Website\ThemeService::class)->cssVariables($tokens) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $themeMode->label() }} Theme Preview</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="theme-preview-page">
        <header class="theme-preview-toolbar"><div><strong>{{ $themeMode->label() }} Theme Preview</strong><span>Published token set</span></div><button type="button" onclick="window.close()">Close preview</button></header>
        <main class="theme-preview-shell">
            <section class="theme-preview-hero"><p class="eyebrow">Beloved College</p><h1>Learning with purpose. Growing with character.</h1><p>This preview demonstrates public headings, text, actions, surfaces, borders and semantic states without changing the live theme.</p><div><a class="primary-link" href="#">Primary action</a><a class="secondary-link" href="#">Secondary action</a></div></section>
            <section class="theme-preview-strip"><div><strong>1,250</strong><span>Students</span></div><div><strong>84</strong><span>Staff</span></div><div><strong>96%</strong><span>Completion</span></div></section>
            <section class="theme-preview-content"><article><p class="eyebrow">Learning</p><h2>Clear information hierarchy</h2><p>Surfaces remain flat and readable. Borders and spacing separate information instead of cascading cards inside cards.</p></article><article><p class="eyebrow">Status</p><h2>Semantic feedback</h2><div class="notice notice-success">A successful action appears clearly.</div><div class="notice notice-error">An error remains distinguishable and readable.</div></article></section>
            <section class="theme-preview-mobile"><div class="preview-mobile-header"><span class="brand-mark">BC</span><strong>Mobile portal</strong></div><div class="preview-mobile-body"><p class="eyebrow">Today</p><h2>Student dashboard</h2><div class="record-list"><div class="record-row compact-record-row"><div><strong>Mathematics assignment</strong><span>Due tomorrow</span></div><strong>Open</strong></div><div class="record-row compact-record-row"><div><strong>Fee balance</strong><span>First Term</span></div><strong>₦25,000</strong></div></div></div></section>
        </main>
    </body>
</html>
