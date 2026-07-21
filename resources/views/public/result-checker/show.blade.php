<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="classic">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $report->student->user->fullName() }} Result | {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="public-result-page">
        <header class="public-result-toolbar">
            <a class="secondary-link" href="{{ route('public.result-checker.index') }}">Check another result</a>
            <button class="primary-button" type="button" onclick="window.print()">Print result</button>
        </header>
        <main class="public-result-sheet">
            @include('reports._card', ['report' => $report, 'subjectRows' => $subjectRows])
        </main>
    </body>
</html>
