@extends('layouts.portal')

@php $routePrefix = request()->routeIs('app.*') ? 'app' : 'web'; @endphp

@section('title', 'CBT')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">Computer-based testing</p>
            <h1>CBT Workspace</h1>
            <p>Create timed examinations, build objective and theory question banks, control availability and review attempts.</p>
        </div>
        <a class="secondary-link" href="{{ route($routePrefix.'.teacher.learning.index') }}">Open Teaching workspace</a>
    </header>

    <section class="metric-row" aria-label="CBT summary">
        <article class="metric-item"><span>Global access</span><strong>{{ $globalEnabled ? 'Enabled' : 'Disabled' }}</strong></article>
        <article class="metric-item"><span>Assessments</span><strong>{{ number_format($assessments->total()) }}</strong></article>
        <article class="metric-item"><span>Active on this page</span><strong>{{ number_format($assessments->where('cbt_is_active', true)->count()) }}</strong></article>
        <article class="metric-item"><span>Attempts on this page</span><strong>{{ number_format($assessments->sum(fn ($assessment) => $assessment->cbtAttempts->count())) }}</strong></article>
    </section>

    @if (auth()->user()->hasAnyRole('super_admin', 'admin', 'principal'))
        <section class="control-strip">
            <div><strong>Global CBT access</strong><span>Disabling this prevents new student attempts without deleting assessments or results.</span></div>
            <form method="POST" action="{{ route($routePrefix.'.teacher.cbt.global-access') }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="enabled" value="{{ $globalEnabled ? 0 : 1 }}">
                <button class="{{ $globalEnabled ? 'danger-button' : 'primary-button' }}" type="submit">{{ $globalEnabled ? 'Disable CBT access' : 'Enable CBT access' }}</button>
            </form>
        </section>
    @endif

    <div class="split-workspace">
        <section class="workspace-primary">
            <div class="section-heading compact-heading"><div><p class="eyebrow">Assessment bank</p><h2>CBT assessments</h2></div></div>
            <div class="record-list">
                @forelse ($assessments as $assessment)
                    <article class="record-row cbt-record-row">
                        <div><strong>{{ $assessment->title }}</strong><span>{{ $assessment->subject->name }} · {{ $assessment->schoolClass->display_name }}</span></div>
                        <div><span>Questions</span><strong>{{ $assessment->cbtQuestions->count() }} · {{ number_format((float) $assessment->total_score, 2) }} points</strong></div>
                        <div><span>Window</span><strong>{{ $assessment->cbt_starts_at?->format('d M Y H:i') ?: 'Open start' }}</strong><small>{{ $assessment->cbt_ends_at?->format('d M Y H:i') ?: 'Open end' }}</small></div>
                        <div><span>Status</span><strong>{{ $assessment->cbt_is_active ? 'Active' : 'Inactive' }}</strong><small>{{ $assessment->cbtAttempts->count() }} attempts</small></div>
                        <div class="record-row-stack"><a href="{{ route($routePrefix.'.teacher.cbt.show', $assessment) }}">Manage CBT</a></div>
                    </article>
                @empty
                    <div class="empty-state">No CBT assessments have been created for your accessible class-and-subject combinations.</div>
                @endforelse
            </div>
            <div class="pagination-wrap">{{ $assessments->links() }}</div>
        </section>

        <aside class="workspace-aside">
            <section class="plain-panel">
                <div class="section-heading compact-heading"><div><p class="eyebrow">New examination</p><h2>Create CBT</h2></div></div>
                <form method="POST" action="{{ route($routePrefix.'.teacher.cbt.store') }}" class="form-stack">
                    @csrf
                    <label class="field-group"><span>Term</span><select name="term_id" required><option value="">Select term</option>@foreach ($terms as $term)<option value="{{ $term->id }}">{{ $term->academicSession?->name }} · {{ $term->name }}</option>@endforeach</select></label>
                    <label class="field-group"><span>Class</span><select name="school_class_id" required><option value="">Select class</option>@foreach ($classes as $class)<option value="{{ $class->id }}">{{ $class->display_name }}</option>@endforeach</select></label>
                    <label class="field-group"><span>Subject</span><select name="subject_id" required><option value="">Select subject</option>@foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label>
                    <label class="field-group"><span>Title</span><input name="title" required></label>
                    <label class="field-group"><span>Assessment type</span><select name="type"><option value="quiz">Quiz</option><option value="test">Test</option><option value="exam">Exam</option><option value="project">Project</option></select></label>
                    <label class="field-group"><span>Duration in minutes</span><input name="cbt_duration_minutes" type="number" min="1" max="600" value="30" required></label>
                    <label class="field-group"><span>Start time</span><input name="cbt_starts_at" type="datetime-local"></label>
                    <label class="field-group"><span>End time</span><input name="cbt_ends_at" type="datetime-local"></label>
                    <label class="field-group"><span>Student instructions</span><textarea name="cbt_instructions" rows="5"></textarea></label>
                    <label class="field-group"><span>Internal notes</span><textarea name="notes" rows="3"></textarea></label>
                    <label class="check-row"><input name="cbt_show_results" type="checkbox" value="1"><span>Show scores when available</span></label>
                    <p class="form-help">The CBT is created inactive. Add questions, then an Admin or Principal can activate it.</p>
                    <button class="primary-button" type="submit">Create CBT</button>
                </form>
            </section>
        </aside>
    </div>
@endsection
