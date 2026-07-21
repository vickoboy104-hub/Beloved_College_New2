<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="classic">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $schoolClass->display_name }} Fee List</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="print-finance-page">
        <div class="print-toolbar"><button type="button" onclick="window.print()">Print fee list</button></div>
        <main class="print-finance-sheet">
            <header class="print-finance-header"><p class="eyebrow">Beloved College</p><h1>{{ $schoolClass->display_name }} Fee List</h1><p>Generated {{ now()->format('d M Y H:i') }}</p></header>
            <table class="print-fee-table"><thead><tr><th>#</th><th>Fee item</th><th>Term</th><th>Due date</th><th>Amount</th></tr></thead><tbody>@forelse ($feeItems as $item)<tr><td>{{ $loop->iteration }}</td><td>{{ $item->name }}@if ($item->description)<small>{{ $item->description }}</small>@endif</td><td>{{ $item->term?->name ?? 'General' }}</td><td>{{ $item->due_date?->format('d M Y') ?: '—' }}</td><td>₦{{ number_format((float) $item->amount, 2) }}</td></tr>@empty<tr><td colspan="5">No fee items are available for this class.</td></tr>@endforelse</tbody><tfoot><tr><th colspan="4">Total</th><th>₦{{ number_format($total, 2) }}</th></tr></tfoot></table>
        </main>
    </body>
</html>
