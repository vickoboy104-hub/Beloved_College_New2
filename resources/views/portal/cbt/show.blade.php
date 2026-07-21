@extends('layouts.portal')

@php $routePrefix = request()->routeIs('app.*') ? 'app' : 'web'; @endphp

@section('title', $assessment->title)

@section('content')
    <header class="page-heading">
        <div>
            <p class="eyebrow">Computer-based assessment</p>
            <h1>{{ $assessment->title }}</h1>
            <p>{{ $assessment->subject->name }} · {{ $student->schoolClass?->display_name }} · {{ $assessment->term?->name }}</p>
        </div>
    </header>

    <section class="exam-status-bar" data-cbt-expiry="{{ $attempt->expires_at?->toIso8601String() }}">
        <div><span>Student</span><strong>{{ $student->user->fullName() }}</strong></div>
        <div><span>Questions</span><strong>{{ $assessment->cbtQuestions->count() }}</strong></div>
        <div><span>Total points</span><strong>{{ number_format((float) $assessment->total_score, 2) }}</strong></div>
        <div><span>Time remaining</span><strong id="cbt-countdown">Calculating…</strong></div>
    </section>

    @if ($assessment->cbt_instructions)
        <section class="exam-instructions"><h2>Instructions</h2><div class="prose-copy">{!! nl2br(e($assessment->cbt_instructions)) !!}</div></section>
    @endif

    <form method="POST" action="{{ route($routePrefix.'.portal.cbt.submit', $assessment) }}" class="cbt-exam-form" id="cbt-exam-form">
        @csrf
        @foreach ($assessment->cbtQuestions as $question)
            @php $saved = $savedAnswers->get($question->id); @endphp
            <fieldset class="exam-question">
                <legend><span>Question {{ $loop->iteration }}</span><strong>{{ number_format((float) $question->points, 2) }} points</strong></legend>
                <div class="question-prompt">{!! nl2br(e($question->prompt)) !!}</div>
                @if (count($question->image_paths ?? []) > 0)
                    <div class="exam-media-grid">@foreach ($question->image_paths as $index => $path)<img src="{{ route($routePrefix.'.private-learning-media.cbt.images', [$question, $index]) }}" alt="Question {{ $loop->parent->iteration }} supporting image {{ $index + 1 }}">@endforeach</div>
                @endif
                <div class="file-link-row">
                    @if ($question->video_path)<a class="secondary-link" href="{{ route($routePrefix.'.private-learning-media.cbt.video', $question) }}" target="_blank">Open question video</a>@endif
                    @if ($question->video_url)<a class="secondary-link" href="{{ $question->video_url }}" target="_blank" rel="noopener">Open external video</a>@endif
                    @if ($question->resource_link)<a class="secondary-link" href="{{ $question->resource_link }}" target="_blank" rel="noopener">Open resource</a>@endif
                </div>
                @if ($question->question_type === 'objective')
                    <div class="answer-options">
                        @foreach ($question->options as $option)
                            <label class="answer-option"><input name="answers[{{ $question->id }}]" type="radio" value="{{ $option->id }}" @checked($saved?->selected_option_id === $option->id)><span>{{ $option->option_text }}</span></label>
                        @endforeach
                    </div>
                @else
                    <label class="field-group"><span>Your answer</span><textarea name="answers[{{ $question->id }}]" rows="8">{{ $saved?->answer_text }}</textarea></label>
                @endif
            </fieldset>
        @endforeach

        <div class="exam-submit-bar">
            <p>Submit only when you are finished. A submitted attempt cannot be reopened.</p>
            <button class="primary-button" type="submit">Submit CBT</button>
        </div>
    </form>

    <script>
        (() => {
            const bar = document.querySelector('[data-cbt-expiry]');
            const output = document.getElementById('cbt-countdown');
            const form = document.getElementById('cbt-exam-form');
            const expiry = bar?.dataset.cbtExpiry ? new Date(bar.dataset.cbtExpiry).getTime() : null;
            if (!expiry || !output) return;
            const tick = () => {
                const remaining = Math.max(0, expiry - Date.now());
                const minutes = Math.floor(remaining / 60000);
                const seconds = Math.floor((remaining % 60000) / 1000);
                output.textContent = `${minutes}:${String(seconds).padStart(2, '0')}`;
                if (remaining <= 0) {
                    output.textContent = 'Time expired';
                    form?.requestSubmit();
                    return;
                }
                window.setTimeout(tick, 1000);
            };
            tick();
        })();
    </script>
@endsection
