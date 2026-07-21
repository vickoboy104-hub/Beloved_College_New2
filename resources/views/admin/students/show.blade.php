@extends('layouts.portal')

@section('title', $student->user->fullName())

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">Student record</p>
            <h1>{{ $student->user->fullName() }}</h1>
            <p>{{ $student->admission_no }} · {{ $student->schoolClass?->display_name ?? 'Class unassigned' }} · {{ $student->academicSession?->name ?? 'Session unassigned' }}</p>
        </div>
        <a class="secondary-link" href="{{ route('web.admin.students.index') }}">Back to students</a>
    </header>

    <section class="identity-strip" aria-label="Student identity summary">
        <div><span>Status</span><strong>{{ $student->archived_at ? 'Archived' : str($student->status)->headline() }}</strong></div>
        <div><span>Student ID</span><strong>{{ $student->student_id_no ?: 'Not assigned' }}</strong></div>
        <div><span>Parent</span><strong>{{ $student->parent?->fullName() ?? $student->guardian_name ?? 'Not linked' }}</strong></div>
        <div><span>Fee balance</span><strong>₦{{ number_format((float) $student->feeInvoices->sum('balance'), 2) }}</strong></div>
    </section>

    <nav class="anchor-navigation" aria-label="Student record sections">
        <a href="#profile">Profile</a>
        <a href="#finance">Finance</a>
        <a href="#reports">Reports</a>
        <a href="#promotions">Promotions</a>
        <a href="#account">Account</a>
    </nav>

    <section class="content-section" id="profile">
        <div class="section-heading">
            <div><p class="eyebrow">Complete record</p><h2>Student profile</h2></div>
            <p>Identity, placement, parent, guardian, medical and previous-school information.</p>
        </div>
        <form method="POST" action="{{ route('web.admin.students.update', $student) }}" enctype="multipart/form-data" class="long-form">
            @csrf
            @method('PATCH')
            @include('admin.students._form', ['student' => $student, 'classes' => $classes])
            <fieldset class="form-section">
                <legend>Account status</legend>
                <div class="form-grid form-grid-2">
                    <label class="field-group">
                        <span>Status</span>
                        <select name="status">
                            <option value="active" @selected(old('status', $student->status) === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $student->status) === 'inactive')>Inactive</option>
                        </select>
                    </label>
                </div>
            </fieldset>
            <div class="form-actions"><button class="primary-button" type="submit">Save student record</button></div>
        </form>
    </section>

    <section class="content-section" id="finance">
        <div class="section-heading">
            <div><p class="eyebrow">Billing history</p><h2>Fee invoices</h2></div>
            <p>Expected amounts, payments and balances remain connected to the permanent student record.</p>
        </div>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead><tr><th>Invoice</th><th>Fee</th><th>Due</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($student->feeInvoices->sortByDesc('issued_at') as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_no }}</td>
                            <td>{{ $invoice->feeItem?->name ?? 'School fee' }}</td>
                            <td>₦{{ number_format((float) $invoice->amount_due, 2) }}</td>
                            <td>₦{{ number_format((float) $invoice->amount_paid, 2) }}</td>
                            <td>₦{{ number_format((float) $invoice->balance, 2) }}</td>
                            <td><span class="status-badge status-{{ $invoice->status }}">{{ str($invoice->status)->headline() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No invoices have been generated for this student.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="content-section" id="reports">
        <div class="section-heading">
            <div><p class="eyebrow">Academic history</p><h2>Term reports</h2></div>
            <p>Approved and published result records will remain available across academic sessions.</p>
        </div>
        <div class="record-list">
            @forelse ($student->termReports->sortByDesc('published_at') as $report)
                <article class="record-row">
                    <div><strong>{{ $report->term?->name ?? 'Term' }}</strong><span>{{ $report->term?->academicSession?->name }}</span></div>
                    <div><span>Average</span><strong>{{ number_format((float) $report->average_score, 2) }}%</strong></div>
                    <div><span>Grade</span><strong>{{ $report->overall_grade ?: 'Pending' }}</strong></div>
                    <div><span>Publication</span><strong>{{ $report->portal_enabled ? 'Portal enabled' : 'Not published' }}</strong></div>
                </article>
            @empty
                <div class="empty-state">No term reports have been compiled yet.</div>
            @endforelse
        </div>
    </section>

    <section class="content-section" id="promotions">
        <div class="section-heading">
            <div><p class="eyebrow">Session movement</p><h2>Promotion history</h2></div>
            <p>Every promotion or repetition remains traceable to its approving administrator.</p>
        </div>
        <div class="record-list">
            @forelse ($student->promotions->sortByDesc('approved_at') as $promotion)
                <article class="record-row">
                    <div><strong>{{ str($promotion->promotion_status)->headline() }}</strong><span>{{ $promotion->fromAcademicSession?->name }} → {{ $promotion->toAcademicSession?->name }}</span></div>
                    <div><span>Average</span><strong>{{ number_format((float) $promotion->overall_percentage, 2) }}%</strong></div>
                    <div><span>Approved</span><strong>{{ $promotion->approved_at?->format('d M Y') }}</strong></div>
                </article>
            @empty
                <div class="empty-state">No promotion history is available.</div>
            @endforelse
        </div>
    </section>

    <section class="content-section danger-zone" id="account">
        <div class="section-heading">
            <div><p class="eyebrow">Account controls</p><h2>Access and archival</h2></div>
            <p>Archival is reversible and preserves academic, financial and personal history.</p>
        </div>
        <div class="inline-actions">
            <form method="POST" action="{{ route('web.admin.students.password.reset', $student) }}">
                @csrf
                <button class="secondary-button" type="submit">Generate temporary password</button>
            </form>

            @if ($student->archived_at)
                <form method="POST" action="{{ route('web.admin.students.restore', $student) }}">
                    @csrf
                    @method('PATCH')
                    <button class="primary-button" type="submit">Restore student record</button>
                </form>
            @else
                <form method="POST" action="{{ route('web.admin.students.archive', $student) }}" class="archive-form">
                    @csrf
                    @method('PATCH')
                    <label class="field-group">
                        <span>Archival reason</span>
                        <input name="reason" required minlength="5" maxlength="500" placeholder="Transfer, withdrawal or another documented reason">
                    </label>
                    <button class="danger-button" type="submit">Archive student</button>
                </form>
            @endif
        </div>
    </section>
@endsection
