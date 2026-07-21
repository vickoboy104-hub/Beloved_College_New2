@extends('layouts.portal')

@php
    $routePrefix = request()->routeIs('app.*') ? 'app' : 'web';
    $isStudentAccount = auth()->user()->hasAnyRole('student');
    $outstanding = (float) $invoices->sum('balance');
    $attendancePresent = $attendance->whereIn('status', [\App\Enums\AttendanceStatus::Present, \App\Enums\AttendanceStatus::Late])->count();
@endphp

@section('title', 'My Portal')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">{{ $isStudentAccount ? 'Student portal' : 'Parent portal' }}</p>
            <h1>{{ $student->user->fullName() }}</h1>
            <p>{{ $student->admission_no }} · {{ $student->schoolClass?->display_name ?? 'Class unassigned' }} · {{ $student->academicSession?->name ?? 'Session unassigned' }}</p>
        </div>
        @if (! $isStudentAccount && $children->count() > 1)
            <form method="GET" action="{{ route($routePrefix.'.portal.index') }}" class="child-switcher">
                <input type="hidden" name="section" value="{{ $activeSection }}">
                <label class="field-group"><span>View child</span><select name="student_id" onchange="this.form.submit()">@foreach ($children as $child)<option value="{{ $child->id }}" @selected($child->id === $student->id)>{{ $child->user->fullName() }} · {{ $child->schoolClass?->display_name }}</option>@endforeach</select></label>
            </form>
        @endif
    </header>

    <section class="metric-row metric-row-5" aria-label="Student portal summary">
        <article class="metric-item"><span>Lessons</span><strong>{{ number_format($lessons->count()) }}</strong></article>
        <article class="metric-item"><span>Assignments</span><strong>{{ number_format($assignments->count()) }}</strong></article>
        <article class="metric-item"><span>Published reports</span><strong>{{ number_format($reports->count()) }}</strong></article>
        <article class="metric-item"><span>Attendance entries</span><strong>{{ number_format($attendance->count()) }}</strong><small>{{ $attendancePresent }} present/late</small></article>
        <article class="metric-item"><span>Fee balance</span><strong>₦{{ number_format($outstanding, 2) }}</strong></article>
    </section>

    <nav class="workspace-tabs" aria-label="Portal sections">
        @foreach ([
            'overview' => 'Overview',
            'lessons' => 'Lessons',
            'assignments' => 'Assignments',
            'results' => 'Results',
            'reports' => 'Report Cards',
            'attendance' => 'Attendance',
            'cbt' => 'CBT',
            'finance' => 'Fees',
        ] as $key => $label)
            <a href="{{ route($routePrefix.'.portal.index', ['section' => $key, 'student_id' => ! $isStudentAccount ? $student->id : null]) }}" @class(['is-active' => $activeSection === $key])>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($activeSection === 'overview')
        <section class="portal-overview-grid">
            <article class="portal-overview-block">
                <p class="eyebrow">Next actions</p>
                <h2>Assignments requiring attention</h2>
                <div class="record-list">
                    @forelse ($assignments->filter(fn ($assignment) => $assignment->submissions->isEmpty() && (! $assignment->due_date || now()->lte($assignment->due_date)))->take(5) as $assignment)
                        <div class="record-row compact-record-row"><div><strong>{{ $assignment->title }}</strong><span>{{ $assignment->subject->name }}</span></div><div><span>Due</span><strong>{{ $assignment->due_date?->format('d M Y H:i') ?: 'No deadline' }}</strong></div></div>
                    @empty
                        <div class="empty-state">No open assignments require attention.</div>
                    @endforelse
                </div>
            </article>
            <article class="portal-overview-block">
                <p class="eyebrow">Recent learning</p>
                <h2>Latest lessons</h2>
                <div class="record-list">
                    @forelse ($lessons->take(5) as $lesson)
                        <div class="record-row compact-record-row"><div><strong>{{ $lesson->title }}</strong><span>{{ $lesson->subject->name }}</span></div><div><span>Published</span><strong>{{ $lesson->published_at?->format('d M Y') }}</strong></div></div>
                    @empty
                        <div class="empty-state">No lessons have been published.</div>
                    @endforelse
                </div>
            </article>
        </section>
    @elseif ($activeSection === 'lessons')
        <section class="content-section">
            <div class="section-heading"><div><p class="eyebrow">Learning materials</p><h2>Lessons</h2></div><p>Notes and media are available only to the assigned class, linked parents and authorized staff.</p></div>
            <div class="record-list">
                @forelse ($lessons as $lesson)
                    <details class="record-disclosure">
                        <summary><span><strong>{{ $lesson->title }}</strong><small>{{ $lesson->subject->name }} · {{ $lesson->teacher->fullName() }}</small></span><span>{{ $lesson->published_at?->format('d M Y') }}</span></summary>
                        <div class="lesson-body">
                            @if ($lesson->summary)<p class="lead-copy">{{ $lesson->summary }}</p>@endif
                            @if ($lesson->body)<div class="prose-copy">{!! nl2br(e($lesson->body)) !!}</div>@endif
                            @if (count($lesson->note_images ?? []) > 0)<div class="file-link-row">@foreach ($lesson->note_images as $index => $path)<a class="secondary-link" href="{{ route($routePrefix.'.private-learning-media.lessons.images', [$lesson, $index]) }}">Open image {{ $index + 1 }}</a>@endforeach</div>@endif
                            <div class="file-link-row">@if ($lesson->video_path)<a class="secondary-link" href="{{ route($routePrefix.'.private-learning-media.lessons.video', $lesson) }}">Open lesson video</a>@endif @if ($lesson->video_url)<a class="secondary-link" href="{{ $lesson->video_url }}" target="_blank" rel="noopener">Open external video</a>@endif @if ($lesson->resource_link)<a class="secondary-link" href="{{ $lesson->resource_link }}" target="_blank" rel="noopener">Open supporting resource</a>@endif</div>
                        </div>
                    </details>
                @empty
                    <div class="empty-state">No lessons are available for this class.</div>
                @endforelse
            </div>
        </section>
    @elseif ($activeSection === 'assignments')
        <section class="content-section">
            <div class="section-heading"><div><p class="eyebrow">Class work</p><h2>Assignments</h2></div><p>{{ $isStudentAccount ? 'Submit typed responses or permitted files before the deadline.' : 'Parents may review assignment status but cannot submit work.' }}</p></div>
            <div class="record-list">
                @forelse ($assignments as $assignment)
                    @php $submission = $assignment->submissions->first(); @endphp
                    <details class="record-disclosure" @if (! $submission) open @endif>
                        <summary><span><strong>{{ $assignment->title }}</strong><small>{{ $assignment->subject->name }} · {{ $assignment->teacher->fullName() }}</small></span><span>{{ $submission ? ($submission->score !== null ? number_format((float) $submission->score, 2).' / '.number_format((float) $assignment->total_score, 2) : 'Submitted') : ($assignment->due_date && now()->gt($assignment->due_date) ? 'Closed' : 'Open') }}</span></summary>
                        <div class="assignment-detail">
                            @if ($assignment->instructions)<div class="prose-copy">{!! nl2br(e($assignment->instructions)) !!}</div>@endif
                            @if (count($assignment->attachment_images ?? []) > 0)<div class="file-link-row">@foreach ($assignment->attachment_images as $index => $path)<a class="secondary-link" href="{{ route($routePrefix.'.private-learning-media.assignments.prompts', [$assignment, $index]) }}">Open prompt image {{ $index + 1 }}</a>@endforeach</div>@endif
                            <dl class="inline-facts"><div><dt>Deadline</dt><dd>{{ $assignment->due_date?->format('d M Y H:i') ?: 'No deadline' }}</dd></div><div><dt>Total score</dt><dd>{{ number_format((float) $assignment->total_score, 2) }}</dd></div><div><dt>Accepted</dt><dd>{{ collect($assignment->allowed_submission_types ?: ['text'])->map(fn ($type) => str($type)->headline())->implode(', ') }}</dd></div></dl>
                            @if ($submission)
                                <div class="submitted-work-summary"><strong>Submitted {{ $submission->submitted_at?->format('d M Y H:i') }}</strong>@if ($submission->content)<p>{{ $submission->content }}</p>@endif @if (count($submission->attachment_paths ?? []) > 0)<div class="file-link-row">@foreach ($submission->attachment_paths as $index => $path)<a class="secondary-link" href="{{ route($routePrefix.'.private-learning-media.submissions.files', [$submission, $index]) }}">Open submitted file {{ $index + 1 }}</a>@endforeach</div>@endif @if ($submission->feedback)<p><strong>Teacher feedback:</strong> {{ $submission->feedback }}</p>@endif</div>
                            @endif
                            @if ($isStudentAccount && (! $assignment->due_date || now()->lte($assignment->due_date)))
                                <form method="POST" action="{{ route($routePrefix.'.portal.assignments.submit', $assignment) }}" enctype="multipart/form-data" class="form-stack submission-form">
                                    @csrf
                                    @if ($assignment->accepts('text'))<label class="field-group"><span>Typed response</span><textarea name="content" rows="6">{{ $submission?->content }}</textarea></label>@endif
                                    @if (collect($assignment->allowed_submission_types ?: ['text'])->contains(fn ($type) => $type !== 'text'))<label class="field-group"><span>Attach files — maximum {{ $assignment->max_submission_files }}</span><input name="files[]" type="file" multiple></label>@endif
                                    <button class="primary-button" type="submit">{{ $submission ? 'Update submission' : 'Submit assignment' }}</button>
                                </form>
                            @endif
                        </div>
                    </details>
                @empty
                    <div class="empty-state">No assignments are available.</div>
                @endforelse
            </div>
        </section>
    @elseif ($activeSection === 'results')
        <div class="data-table-wrap"><table class="data-table"><thead><tr><th>Assessment</th><th>Subject</th><th>Term</th><th>Score</th><th>Grade</th><th>Remark</th></tr></thead><tbody>@forelse ($assessmentResults as $result)<tr><td><strong>{{ $result->assessment->title }}</strong><small>{{ $result->assessment->type->label() }}</small></td><td>{{ $result->assessment->subject?->name }}</td><td>{{ $result->assessment->term?->name }}</td><td>{{ number_format((float) $result->score, 2) }} / {{ number_format((float) $result->assessment->total_score, 2) }}</td><td>{{ $result->grade ?: '—' }}</td><td>{{ $result->remark ?: '—' }}</td></tr>@empty<tr><td colspan="6">No assessment results are available.</td></tr>@endforelse</tbody></table></div>
    @elseif ($activeSection === 'reports')
        <div class="record-list">@forelse ($reports as $report)<article class="record-row"><div><strong>{{ $report->term->academicSession?->name }} · {{ $report->term->name }}</strong><span>Published {{ $report->published_at?->format('d M Y') }}</span></div><div><span>Average</span><strong>{{ number_format((float) $report->average_score, 2) }}%</strong></div><div><span>Grade</span><strong>{{ $report->overall_grade }}</strong></div><div class="record-row-stack"><a href="{{ route($routePrefix.'.portal.reports.show', [$report, 'student_id' => ! $isStudentAccount ? $student->id : null]) }}">Open report card</a></div></article>@empty<div class="empty-state">No private report cards have been published.</div>@endforelse</div>
    @elseif ($activeSection === 'attendance')
        <div class="data-table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Status</th><th>Class</th><th>Note</th><th>Taken by</th></tr></thead><tbody>@forelse ($attendance as $record)<tr><td>{{ $record->attendance_date->format('d M Y') }}</td><td><span class="status-badge status-{{ in_array($record->status->value, ['present','late']) ? 'active' : 'inactive' }}">{{ $record->status->label() }}</span></td><td>{{ $record->schoolClass?->display_name }}</td><td>{{ $record->note ?: '—' }}</td><td>{{ $record->takenBy?->fullName() }}</td></tr>@empty<tr><td colspan="5">No attendance records are available.</td></tr>@endforelse</tbody></table></div>
    @elseif ($activeSection === 'cbt')
        <div class="record-list">@forelse ($cbtAssessments as $assessment)@php $attempt = $assessment->cbtAttempts->first(); @endphp<article class="record-row cbt-record-row"><div><strong>{{ $assessment->title }}</strong><span>{{ $assessment->subject->name }} · {{ $assessment->term?->name }}</span></div><div><span>Duration</span><strong>{{ $assessment->cbt_duration_minutes }} minutes</strong></div><div><span>Window</span><strong>{{ $assessment->cbt_starts_at?->format('d M Y H:i') ?: 'Open' }}</strong><small>{{ $assessment->cbt_ends_at?->format('d M Y H:i') ?: 'No closing time' }}</small></div><div><span>Status</span><strong>{{ $attempt?->status ? str($attempt->status)->headline() : 'Not started' }}</strong></div><div class="record-row-stack">@if ($attempt?->submitted_at)<a href="{{ route($routePrefix.'.portal.cbt.result', [$attempt, 'student_id' => ! $isStudentAccount ? $student->id : null]) }}">View attempt</a>@elseif ($isStudentAccount)<a href="{{ route($routePrefix.'.portal.cbt.show', $assessment) }}">Start or resume CBT</a>@else<span>Student must open the attempt</span>@endif</div></article>@empty<div class="empty-state">No active CBT assessments are available.</div>@endforelse</div>
    @elseif ($activeSection === 'finance')
        <div class="data-table-wrap"><table class="data-table"><thead><tr><th>Invoice</th><th>Fee</th><th>Due</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody>@forelse ($invoices as $invoice)<tr><td>{{ $invoice->invoice_no }}</td><td>{{ $invoice->feeItem?->name ?? 'School fee' }}</td><td>₦{{ number_format((float) $invoice->amount_due, 2) }}</td><td>₦{{ number_format((float) $invoice->amount_paid, 2) }}</td><td>₦{{ number_format((float) $invoice->balance, 2) }}</td><td><span class="status-badge status-{{ $invoice->status }}">{{ str($invoice->status)->headline() }}</span></td></tr>@empty<tr><td colspan="6">No fee invoices are available.</td></tr>@endforelse</tbody></table></div>
    @endif
@endsection
