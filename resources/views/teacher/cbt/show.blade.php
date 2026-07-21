@extends('layouts.portal')

@php $routePrefix = request()->routeIs('app.*') ? 'app' : 'web'; @endphp

@section('title', $assessment->title)

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">CBT assessment</p>
            <h1>{{ $assessment->title }}</h1>
            <p>{{ $assessment->subject->name }} · {{ $assessment->schoolClass->display_name }} · {{ $assessment->term?->academicSession?->name }} {{ $assessment->term?->name }}</p>
        </div>
        <a class="secondary-link" href="{{ route($routePrefix.'.teacher.cbt.index') }}">Back to CBT workspace</a>
    </header>

    <section class="identity-strip" aria-label="CBT summary">
        <div><span>Status</span><strong>{{ $assessment->cbt_is_active ? 'Active' : 'Inactive' }}</strong></div>
        <div><span>Duration</span><strong>{{ $assessment->cbt_duration_minutes }} minutes</strong></div>
        <div><span>Questions</span><strong>{{ $assessment->cbtQuestions->count() }}</strong></div>
        <div><span>Total points</span><strong>{{ number_format((float) $assessment->total_score, 2) }}</strong></div>
    </section>

    @if (auth()->user()->hasAnyRole('super_admin', 'admin', 'principal'))
        <section class="control-strip">
            <div><strong>{{ $assessment->cbt_is_active ? 'This CBT is active' : 'This CBT is inactive' }}</strong><span>Question editing locks automatically after the first student attempt.</span></div>
            <form method="POST" action="{{ route($routePrefix.'.teacher.cbt.active', $assessment) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="active" value="{{ $assessment->cbt_is_active ? 0 : 1 }}">
                <button class="{{ $assessment->cbt_is_active ? 'danger-button' : 'primary-button' }}" type="submit">{{ $assessment->cbt_is_active ? 'Deactivate CBT' : 'Activate CBT' }}</button>
            </form>
        </section>
    @endif

    <nav class="anchor-navigation" aria-label="CBT sections">
        <a href="#questions">Question bank</a>
        <a href="#new-question">Add question</a>
        <a href="#attempts">Attempts</a>
        <a href="#theory-grading">Theory grading</a>
    </nav>

    <section class="content-section" id="questions">
        <div class="section-heading"><div><p class="eyebrow">Question bank</p><h2>Questions and answer options</h2></div><p>Objective questions auto-grade. Theory answers remain pending until reviewed.</p></div>
        <div class="record-list">
            @forelse ($assessment->cbtQuestions as $question)
                <details class="record-disclosure">
                    <summary>
                        <span><strong>{{ $loop->iteration }}. {{ str($question->prompt)->limit(110) }}</strong><small>{{ str($question->question_type)->headline() }} · {{ number_format((float) $question->points, 2) }} points</small></span>
                        <span>{{ $question->options->count() }} options</span>
                    </summary>
                    <div class="question-editor">
                        @if (count($question->image_paths ?? []) > 0)
                            <div class="file-link-row">@foreach ($question->image_paths as $index => $path)<a class="secondary-link" href="{{ route($routePrefix.'.private-learning-media.cbt.images', [$question, $index]) }}">Open image {{ $index + 1 }}</a>@endforeach</div>
                        @endif
                        @if ($question->video_path)<a class="secondary-link" href="{{ route($routePrefix.'.private-learning-media.cbt.video', $question) }}">Open uploaded video</a>@endif
                        @if ($question->video_url)<a class="secondary-link" href="{{ $question->video_url }}" target="_blank" rel="noopener">Open external video</a>@endif
                        <form method="POST" action="{{ route($routePrefix.'.teacher.cbt.questions.update', $question) }}" enctype="multipart/form-data" class="long-form compact-long-form">
                            @csrf
                            @method('PATCH')
                            <div class="form-grid form-grid-2">
                                <label class="field-group"><span>Question type</span><select name="question_type"><option value="objective" @selected($question->question_type === 'objective')>Objective</option><option value="theory" @selected($question->question_type === 'theory')>Theory</option></select></label>
                                <label class="field-group"><span>Points</span><input name="points" type="number" min="0.01" step="0.01" value="{{ $question->points }}" required></label>
                                <label class="field-group form-span-full"><span>Prompt</span><textarea name="prompt" rows="4" required>{{ $question->prompt }}</textarea></label>
                                <label class="field-group"><span>Replacement images</span><input name="image_files[]" type="file" accept="image/*" multiple></label>
                                <label class="field-group"><span>Replacement video</span><input name="video_file" type="file" accept="video/mp4,video/webm,video/quicktime"></label>
                                <label class="field-group"><span>External video URL</span><input name="video_url" type="url" value="{{ $question->video_url }}"></label>
                                <label class="field-group"><span>Resource URL</span><input name="resource_link" type="url" value="{{ $question->resource_link }}"></label>
                                <label class="field-group form-span-full"><span>Theory sample answer</span><textarea name="theory_sample_answer" rows="3">{{ $question->theory_sample_answer }}</textarea></label>
                            </div>
                            @if ($question->question_type === 'objective')
                                <fieldset class="compact-fieldset"><legend>Answer options — select exactly one correct option</legend>
                                    <div class="option-editor-grid">
                                        @foreach ($question->options->values() as $optionIndex => $option)
                                            <label class="field-group"><span>Option {{ $optionIndex + 1 }}</span><input name="options[{{ $optionIndex }}][text]" value="{{ $option->option_text }}" required></label>
                                            <label class="check-row"><input name="options[{{ $optionIndex }}][is_correct]" type="checkbox" value="1" @checked($option->is_correct)><span>Correct</span></label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endif
                            <div class="form-actions"><button class="secondary-button" type="submit">Save question</button></div>
                        </form>
                        <form method="POST" action="{{ route($routePrefix.'.teacher.cbt.questions.destroy', $question) }}" class="delete-row">
                            @csrf
                            @method('DELETE')
                            <button class="text-button danger-text" type="submit">Delete question</button>
                        </form>
                    </div>
                </details>
            @empty
                <div class="empty-state">No questions have been added.</div>
            @endforelse
        </div>
    </section>

    <details class="action-panel" id="new-question" open>
        <summary><span><strong>Add a CBT question</strong><small>Use objective options for automatic grading or a theory sample answer for manual review.</small></span><span aria-hidden="true">+</span></summary>
        <form method="POST" action="{{ route($routePrefix.'.teacher.cbt.questions.store', $assessment) }}" enctype="multipart/form-data" class="long-form">
            @csrf
            <div class="form-grid form-grid-2">
                <label class="field-group"><span>Question type</span><select name="question_type"><option value="objective">Objective</option><option value="theory">Theory</option></select></label>
                <label class="field-group"><span>Points</span><input name="points" type="number" min="0.01" step="0.01" value="1" required></label>
                <label class="field-group form-span-full"><span>Question prompt</span><textarea name="prompt" rows="5" required></textarea></label>
                <label class="field-group"><span>Question images</span><input name="image_files[]" type="file" accept="image/*" multiple></label>
                <label class="field-group"><span>Question video</span><input name="video_file" type="file" accept="video/mp4,video/webm,video/quicktime"></label>
                <label class="field-group"><span>External video URL</span><input name="video_url" type="url"></label>
                <label class="field-group"><span>Resource URL</span><input name="resource_link" type="url"></label>
                <label class="field-group form-span-full"><span>Theory sample answer</span><textarea name="theory_sample_answer" rows="3"></textarea></label>
            </div>
            <fieldset class="compact-fieldset"><legend>Objective options — leave blank for theory questions</legend>
                <div class="option-editor-grid">
                    @for ($index = 0; $index < 4; $index++)
                        <label class="field-group"><span>Option {{ $index + 1 }}</span><input name="options[{{ $index }}][text]"></label>
                        <label class="check-row"><input name="options[{{ $index }}][is_correct]" type="checkbox" value="1"><span>Correct</span></label>
                    @endfor
                </div>
            </fieldset>
            <div class="form-actions"><button class="primary-button" type="submit">Add question</button></div>
        </form>
    </details>

    <section class="content-section" id="attempts">
        <div class="section-heading"><div><p class="eyebrow">Student activity</p><h2>Attempts</h2></div><p>Objective scores are automatic; final status becomes graded after every theory answer is reviewed.</p></div>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead><tr><th>Student</th><th>Status</th><th>Started</th><th>Submitted</th><th>Objective</th><th>Theory</th><th>Total</th></tr></thead>
                <tbody>
                    @forelse ($assessment->cbtAttempts as $attempt)
                        <tr><td><strong>{{ $attempt->student->user->fullName() }}</strong><small>{{ $attempt->student->admission_no }}</small></td><td><span class="status-badge status-{{ $attempt->status === 'graded' ? 'active' : 'inactive' }}">{{ str($attempt->status)->headline() }}</span></td><td>{{ $attempt->started_at?->format('d M Y H:i') }}</td><td>{{ $attempt->submitted_at?->format('d M Y H:i') ?: 'Not submitted' }}</td><td>{{ number_format((float) $attempt->objective_score, 2) }}</td><td>{{ number_format((float) $attempt->theory_score, 2) }}</td><td>{{ number_format((float) $attempt->total_score, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="7">No students have started this CBT.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="content-section" id="theory-grading">
        <div class="section-heading"><div><p class="eyebrow">Manual review</p><h2>Pending theory answers</h2></div><p>Scores cannot exceed the question points.</p></div>
        <div class="record-list">
            @forelse ($pendingTheoryAnswers as $answer)
                <details class="record-disclosure" open>
                    <summary><span><strong>{{ $answer->attempt->student->user->fullName() }}</strong><small>{{ str($answer->question->prompt)->limit(120) }}</small></span><span>Maximum {{ number_format((float) $answer->question->points, 2) }}</span></summary>
                    <div class="submission-review">
                        <div class="submitted-text"><h3>Student answer</h3><p>{{ $answer->answer_text ?: 'No written response was submitted.' }}</p></div>
                        @if ($answer->question->theory_sample_answer)<div class="submitted-text sample-answer"><h3>Sample answer</h3><p>{{ $answer->question->theory_sample_answer }}</p></div>@endif
                        <form method="POST" action="{{ route($routePrefix.'.teacher.cbt.answers.grade', $answer) }}" class="form-grid form-grid-2">
                            @csrf
                            @method('PATCH')
                            <label class="field-group"><span>Score</span><input name="score" type="number" min="0" max="{{ $answer->question->points }}" step="0.01" required></label>
                            <label class="field-group"><span>Feedback</span><textarea name="feedback" rows="3"></textarea></label>
                            <div class="form-actions form-span-full"><button class="primary-button" type="submit">Save theory grade</button></div>
                        </form>
                    </div>
                </details>
            @empty
                <div class="empty-state">There are no pending theory answers.</div>
            @endforelse
        </div>
    </section>
@endsection
