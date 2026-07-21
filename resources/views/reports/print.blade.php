<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="classic">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $report->student->user->fullName() }} Report Card</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="print-report-page">
        <div class="print-toolbar"><button type="button" onclick="window.print()">Print report</button></div>
        <main class="print-report-sheet">
            @include('reports._card', ['report' => $report, 'subjectRows' => $subjectRows])
        </main>
    </body>
</html>
