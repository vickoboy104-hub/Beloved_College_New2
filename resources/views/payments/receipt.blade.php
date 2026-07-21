@extends('layouts.portal')

@php
    $routePrefix = request()->routeIs('app.*') ? 'app' : 'web';
    $allocated = collect(data_get($payment->payload, 'allocated_invoices', []));
@endphp

@section('title', 'Payment Receipt')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">Payment receipt</p>
            <h1>{{ $payment->receipt_no ?: 'Receipt pending' }}</h1>
            <p>{{ $payment->student->user->fullName() }} · {{ $payment->student->admission_no }}</p>
        </div>
        <div class="inline-actions">
            <a class="secondary-link" href="{{ route($routePrefix.'.payments.index', ['student_id' => $payment->student_id]) }}">Back to payments</a>
            <button class="primary-button" type="button" onclick="window.print()">Print receipt</button>
        </div>
    </header>

    <article class="payment-receipt">
        <header class="payment-receipt-header">
            <div><p class="eyebrow">Beloved College</p><h2>Official Payment Receipt</h2></div>
            <strong>{{ $payment->receipt_no }}</strong>
        </header>

        <dl class="receipt-facts">
            <div><dt>Student</dt><dd>{{ $payment->student->user->fullName() }}</dd></div>
            <div><dt>Admission number</dt><dd>{{ $payment->student->admission_no }}</dd></div>
            <div><dt>Class</dt><dd>{{ $payment->student->schoolClass?->display_name ?? 'Unassigned' }}</dd></div>
            <div><dt>Payment date</dt><dd>{{ $payment->paid_at?->format('d M Y H:i') }}</dd></div>
            <div><dt>Provider</dt><dd>{{ $payment->provider->label() }}</dd></div>
            <div><dt>Channel</dt><dd>{{ str($payment->channel ?: 'Unspecified')->headline() }}</dd></div>
            <div><dt>Reference</dt><dd>{{ $payment->reference }}</dd></div>
            <div><dt>Gateway reference</dt><dd>{{ $payment->gateway_reference ?: '—' }}</dd></div>
        </dl>

        <section class="receipt-amount">
            <span>Amount received</span>
            <strong>₦{{ number_format((float) $payment->amount, 2) }}</strong>
            <small>{{ strtoupper($payment->currency) }}</small>
        </section>

        @if ($payment->feeInvoice)
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Invoice</th><th>Fee item</th><th>Amount due</th><th>Paid to date</th><th>Balance</th></tr></thead>
                    <tbody><tr><td>{{ $payment->feeInvoice->invoice_no }}</td><td>{{ $payment->feeInvoice->feeItem?->name ?? 'School fee' }}</td><td>₦{{ number_format((float) $payment->feeInvoice->amount_due, 2) }}</td><td>₦{{ number_format((float) $payment->feeInvoice->amount_paid, 2) }}</td><td>₦{{ number_format((float) $payment->feeInvoice->balance, 2) }}</td></tr></tbody>
                </table>
            </div>
        @elseif ($allocated->isNotEmpty())
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Invoice</th><th>Fee item</th><th>Paid now</th><th>Paid to date</th><th>Balance</th></tr></thead>
                    <tbody>@foreach ($allocated as $row)<tr><td>{{ $row['invoice_no'] }}</td><td>{{ $row['fee_item'] }}</td><td>₦{{ number_format((float) $row['amount_paid_now'], 2) }}</td><td>₦{{ number_format((float) $row['amount_paid_total'], 2) }}</td><td>₦{{ number_format((float) $row['balance'], 2) }}</td></tr>@endforeach</tbody>
                </table>
            </div>
        @endif

        <footer class="payment-receipt-footer">
            <span>{{ $payment->recorder ? 'Recorded by '.$payment->recorder->fullName() : 'Verified electronically by '.$payment->provider->label() }}</span>
            <span>Generated {{ now()->format('d M Y H:i') }}</span>
        </footer>
    </article>
@endsection
