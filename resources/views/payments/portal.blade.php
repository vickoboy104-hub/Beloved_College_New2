@extends('layouts.portal')

@php
    $routePrefix = request()->routeIs('app.*') ? 'app' : 'web';
    $isParent = auth()->user()->hasAnyRole('parent');
    $unpaid = $invoices->where('balance', '>', 0);
@endphp

@section('title', 'Payments')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">School fees</p>
            <h1>{{ $student->user->fullName() }}</h1>
            <p>{{ $student->admission_no }} · {{ $student->schoolClass?->display_name ?? 'Class unassigned' }}</p>
        </div>
        @if ($isParent && $children->count() > 1)
            <form method="GET" action="{{ route($routePrefix.'.payments.index') }}" class="child-switcher">
                <label class="field-group"><span>View child</span><select name="student_id" onchange="this.form.submit()">@foreach ($children as $child)<option value="{{ $child->id }}" @selected($child->id === $student->id)>{{ $child->user->fullName() }} · {{ $child->schoolClass?->display_name }}</option>@endforeach</select></label>
            </form>
        @endif
    </header>

    <section class="metric-row" aria-label="Payment summary">
        <article class="metric-item"><span>Total billed</span><strong>₦{{ number_format((float) $invoices->sum('amount_due'), 2) }}</strong></article>
        <article class="metric-item"><span>Total paid</span><strong>₦{{ number_format((float) $invoices->sum('amount_paid'), 2) }}</strong></article>
        <article class="metric-item"><span>Outstanding</span><strong>₦{{ number_format((float) $invoices->sum('balance'), 2) }}</strong></article>
        <article class="metric-item"><span>Receipts</span><strong>{{ number_format($payments->count()) }}</strong></article>
    </section>

    <section class="content-section">
        <div class="section-heading"><div><p class="eyebrow">Outstanding fees</p><h2>Select invoices to pay</h2></div><p>The school never receives or stores your card details. Checkout is completed on the selected provider's secure page.</p></div>

        @if ($unpaid->isNotEmpty())
            <form method="POST" class="invoice-selection-form">
                @csrf
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Select</th><th>Invoice</th><th>Fee item</th><th>Term</th><th>Due date</th><th>Paid</th><th>Balance</th></tr></thead>
                        <tbody>
                            @foreach ($unpaid as $invoice)
                                <tr>
                                    <td data-label="Select"><input name="invoice_ids[]" type="checkbox" value="{{ $invoice->id }}"></td>
                                    <td data-label="Invoice"><strong>{{ $invoice->invoice_no }}</strong></td>
                                    <td data-label="Fee item">{{ $invoice->feeItem?->name ?? 'School fee' }}</td>
                                    <td data-label="Term">{{ $invoice->feeItem?->term?->name ?? 'General' }}</td>
                                    <td data-label="Due date">{{ $invoice->due_date?->format('d M Y') ?: 'Not set' }}</td>
                                    <td data-label="Paid">₦{{ number_format((float) $invoice->amount_paid, 2) }}</td>
                                    <td data-label="Balance"><strong>₦{{ number_format((float) $invoice->balance, 2) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="gateway-choice-bar">
                    <div><strong>Choose a verified payment provider</strong><span>Select one or more invoices above, then continue with an available gateway.</span></div>
                    <div class="gateway-buttons">
                        @forelse ($gateways as $gateway)
                            <button
                                class="primary-button"
                                type="submit"
                                formaction="{{ route($routePrefix.'.payments.checkout-selection', $gateway['value']) }}"
                            >Pay with {{ $gateway['label'] }}</button>
                        @empty
                            <span class="status-badge status-inactive">No online gateway is currently available</span>
                        @endforelse
                    </div>
                </div>
            </form>
        @else
            <div class="empty-state">There are no outstanding invoices for this student.</div>
        @endif
    </section>

    <section class="content-section">
        <div class="section-heading"><div><p class="eyebrow">Payment history</p><h2>Receipts</h2></div><p>Receipts remain available to the student, linked parent and authorized finance staff.</p></div>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead><tr><th>Receipt</th><th>Date</th><th>Provider</th><th>Channel</th><th>Amount</th><th><span class="sr-only">Action</span></th></tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td data-label="Receipt"><strong>{{ $payment->receipt_no }}</strong><small>{{ $payment->reference }}</small></td>
                            <td data-label="Date">{{ $payment->paid_at?->format('d M Y H:i') }}</td>
                            <td data-label="Provider">{{ $payment->provider->label() }}</td>
                            <td data-label="Channel">{{ str($payment->channel ?: 'Unspecified')->headline() }}</td>
                            <td data-label="Amount">₦{{ number_format((float) $payment->amount, 2) }}</td>
                            <td class="table-actions"><a href="{{ route($routePrefix.'.payments.receipt', $payment) }}">Open receipt</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No completed payments are available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
