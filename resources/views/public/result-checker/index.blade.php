<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="classic">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Result Checker | {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="public-checker-page">
        <main class="public-checker-shell">
            <header class="public-checker-header">
                <a class="portal-brand" href="{{ route('public.home') }}"><span class="brand-mark">BC</span><span><strong>Beloved College</strong><small>Result Checker</small></span></a>
                <p class="eyebrow">Secure academic verification</p>
                <h1>Check a published result</h1>
                <p>Enter the student's admission number, select the academic term and provide the result PIN issued by the school.</p>
            </header>

            @if ($errors->any())
                <div class="notice notice-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('public.result-checker.lookup') }}" class="public-checker-form">
                @csrf
                <label class="field-group"><span>Admission number</span><input name="admission_no" value="{{ old('admission_no') }}" autocomplete="off" required></label>
                <label class="field-group"><span>Academic term</span><select name="term_id" required><option value="">Select term</option>@foreach ($terms as $term)<option value="{{ $term->id }}" @selected((string) old('term_id') === (string) $term->id)>{{ $term->academicSession?->name }} · {{ $term->name }}</option>@endforeach</select></label>
                <label class="field-group"><span>Result PIN</span><input name="pin" type="password" inputmode="numeric" autocomplete="off" required></label>
                <button class="primary-button" type="submit">Check result</button>
            </form>

            <p class="public-checker-help">For privacy, incorrect admission numbers and incorrect PINs receive the same error message. Contact the school office when access details are unavailable.</p>
        </main>
    </body>
</html>
