@extends('layouts.portal')

@php $routePrefix = request()->routeIs('app.*') ? 'app' : 'web'; @endphp

@section('title', 'CBT Result')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">CBT attempt</p>
            <h1>{{ $attempt->assessment->title }}</h1>
            <p>{{ $student->user->fullName() }} · {{ $attempt->assessment->subject->name }} · {{ $attempt->assessment->term?->academicSession?->name }} {{ $attempt->assessment->term?->name }}</p>
        </div>
        <a class="secondary-link" href="{{ route($routePrefix.'.portal.index', ['section' => 'cbt', 'student_id' => request('student_id')]) }}">Back to CBT</a>
    </header>

    <section class="identity-strip">
        <div><span>Status</span><strong>{{ str($attempt->status)->headline() }}</strong></div>
        <div><span>Submitted</span><strong>{{ $attempt->submitted_at?->format('d M Y H:i') ?: 'Not submitted' }}</strong></div>
        <div><span>Objective</span><strong>{{ $showScores ? number_format((float) $attempt->objective_score, 2) : 'Hidden' }}</strong></div>
        <div><span>Total</span><strong>{{ $showScores ? number_format((float) $attempt->total_score, 2).' / '.number_format((float) $attempt->assessment->total_score, 2) : 'Pending release' }}</strong></div>
    </section>

    @if ($attempt->status !== 'graded')
        <div class="notice notice-success" role="status">Objective answers have been processed. Theory answers remain pending until the teacher completes manual grading.</div>
    @endif

    <section class="content-section">
        <div class="section-heading"><div><p class="eyebrow">Response summary</p><h2>Submitted answers</h2></div><p>Correct-answer details are not exposed. Teacher feedback appears after theory grading.</p></div>
        <div class="record-list">
            @forelse ($attempt->answers as $answer)
                <article class="record-row cbt-answer-row">
                    <div><strong>{{ str($answer->question->prompt)->limit(120) }}</strong><span>{{ str($answer->question->question_type)->headline() }}</span></div>
                    <div><span>Response</span><strong>{{ $answer->question->question_type === 'objective' ? ($answer->selected_option_id ? 'Option submitted' : 'No option') : str($answer->answer_text)->limit(100) }}</strong></div>
                    <div><span>Score</span><strong>{{ $showScores && $answer->awarded_score !== null ? number_format((float) $answer->awarded_score, 2).' / '.number_format((float) $answer->question->points, 2) : 'Pending/hidden' }}</strong></div>
                    <div><span>Feedback</span><strong>{{ $answer->feedback ?: '—' }}</strong></div>
                </article>
            @empty
                <div class="empty-state">No submitted answers are available.</div>
            @endforelse
        </div>
    </section>
@endsection
