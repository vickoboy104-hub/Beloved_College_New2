@extends('layouts.portal')

@php
    $routePrefix = request()->routeIs('app.*') ? 'app' : 'web';
    $studentsByClass = $students->groupBy('school_class_id');
    $ordinaryAssessments = $assessments->where('is_cbt', false);
@endphp

@section('title', 'Teaching')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">Teaching workspace</p>
            <h1>Learning and Assessment</h1>
            <p>Publish lessons, issue assignments, take attendance, record results and grade submitted work.</p>
        </div>
        <a class="secondary-link" href="{{ route($routePrefix.'.teacher.cbt.index') }}">Open CBT workspace</a>
    </header>

    <section class="metric-row metric-row-5" aria-label="Teaching summary">
        <article class="metric-item"><span>Accessible classes</span><strong>{{ number_format($classes->count()) }}</strong></article>
        <article class="metric-item"><span>Subjects</span><strong>{{ number_format($subjects->count()) }}</strong></article>
        <article class="metric-item"><span>Lessons</span><strong>{{ number_format($lessons->count()) }}</strong></article>
        <article class="metric-item"><span>Assignments</span><strong>{{ number_format($assignments->count()) }}</strong></article>
        <article class="metric-item"><span>Pending submissions</span><strong>{{ number_format($submissions->whereNull('score')->count()) }}</strong></article>
    </section>

    <nav class="workspace-tabs" aria-label="Teaching sections">
        @foreach ([
            'lessons' => 'Lessons',
            'assignments' => 'Assignments',
            'attendance' => 'Attendance',
            'results' => 'Assessment Results',
            'submissions' => 'Submission Grading',
        ] as $key => $label)
            <a href="{{ route($routePrefix.'.teacher.learning.index', ['section' => $key]) }}" @class(['is-active' => $activeSection === $key])>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($activeSection === 'lessons')
        <div class="split-workspace">
            <section class="workspace-primary">
                <div class="section-heading compact-heading"><div><p class="eyebrow">Published content</p><h2>Recent lessons</h2></div></div>
                <div class="record-list">
                    @forelse ($lessons as $lesson)
                        <article class="record-row learning-record-row">
                            <div><strong>{{ $lesson->title }}</strong><span>{{ $lesson->subject->name }} · {{ $lesson->schoolClass->display_name }}</span></div>
                            <div><span>Published</span><strong>{{ $lesson->published_at?->format('d M Y H:i') }}</strong></div>
                            <div><span>Media</span><strong>{{ count($lesson->note_images ?? []) }} images{{ $lesson->video_path || $lesson->video_url ? ' · Video' : '' }}</strong></div>
                            <div class="record-row-stack">
                                @if ($lesson->video_path)<a href="{{ route($routePrefix.'.private-learning-media.lessons.video', $lesson) }}">Open uploaded video</a>@endif
                                @if ($lesson->video_url)<a href="{{ $lesson->video_url }}" target="_blank" rel="noopener">Open external video</a>@endif
                                @if ($lesson->resource_link)<a href="{{ $lesson->resource_link }}" target="_blank" rel="noopener">Open resource</a>@endif
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">No lessons have been published for your accessible class-and-subject combinations.</div>
                    @endforelse
                </div>
            </section>
            <aside class="workspace-aside">
                <section class="plain-panel">
                    <div class="section-heading compact-heading"><div><p class="eyebrow">New content</p><h2>Publish lesson</h2></div></div>
                    <form method="POST" action="{{ route($routePrefix.'.teacher.learning.lessons.store') }}" enctype="multipart/form-data" class="form-stack">
                        @csrf
                        <label class="field-group"><span>Class</span><select name="school_class_id" required><option value="">Select class</option>@foreach ($classes as $class)<option value="{{ $class->id }}">{{ $class->display_name }}</option>@endforeach</select></label>
                        <label class="field-group"><span>Subject</span><select name="subject_id" required><option value="">Select subject</option>@foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label>
                        <label class="field-group"><span>Lesson title</span><input name="title" required></label>
                        <label class="field-group"><span>Short summary</span><textarea name="summary" rows="2"></textarea></label>
                        <label class="field-group"><span>Lesson note</span><textarea name="body" rows="8"></textarea></label>
                        <label class="field-group"><span>Lesson images</span><input name="note_images[]" type="file" accept="image/*" multiple></label>
                        <label class="field-group"><span>Upload video</span><input name="video_file" type="file" accept="video/mp4,video/webm,video/quicktime"></label>
                        <label class="field-group"><span>External video URL</span><input name="video_url" type="url"></label>
                        <label class="field-group"><span>Supporting resource URL</span><input name="resource_link" type="url"></label>
                        <button class="primary-button" type="submit">Publish lesson</button>
                    </form>
                </section>
            </aside>
        </div>
    @elseif ($activeSection === 'assignments')
        <div class="split-workspace">
            <section class="workspace-primary">
                <div class="section-heading compact-heading"><div><p class="eyebrow">Assigned work</p><h2>Recent assignments</h2></div></div>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Assignment</th><th>Class</th><th>Due</th><th>Score</th><th>Submission types</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($assignments as $assignment)
                                <tr>
                                    <td><strong>{{ $assignment->title }}</strong><small>{{ $assignment->subject->name }}</small></td>
                                    <td>{{ $assignment->schoolClass->display_name }}</td>
                                    <td>{{ $assignment->due_date?->format('d M Y H:i') ?: 'No deadline' }}</td>
                                    <td>{{ number_format((float) $assignment->total_score, 2) }}</td>
                                    <td>{{ collect($assignment->allowed_submission_types ?: ['text'])->map(fn ($type) => str($type)->headline())->implode(', ') }}</td>
                                    <td><span class="status-badge status-{{ $assignment->status === 'published' ? 'active' : 'inactive' }}">{{ str($assignment->status)->headline() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No assignments are available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <aside class="workspace-aside">
                <section class="plain-panel">
                    <div class="section-heading compact-heading"><div><p class="eyebrow">New task</p><h2>Create assignment</h2></div></div>
                    <form method="POST" action="{{ route($routePrefix.'.teacher.learning.assignments.store') }}" enctype="multipart/form-data" class="form-stack">
                        @csrf
                        <label class="field-group"><span>Class</span><select name="school_class_id" required><option value="">Select class</option>@foreach ($classes as $class)<option value="{{ $class->id }}">{{ $class->display_name }}</option>@endforeach</select></label>
                        <label class="field-group"><span>Subject</span><select name="subject_id" required><option value="">Select subject</option>@foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label>
                        <label class="field-group"><span>Title</span><input name="title" required></label>
                        <label class="field-group"><span>Instructions</span><textarea name="instructions" rows="6"></textarea></label>
                        <label class="field-group"><span>Prompt images</span><input name="attachment_images[]" type="file" accept="image/*" multiple></label>
                        <label class="field-group"><span>Deadline</span><input name="due_date" type="datetime-local"></label>
                        <label class="field-group"><span>Total score</span><input name="total_score" type="number" min="1" step="0.01" value="100" required></label>
                        <label class="field-group"><span>Status</span><select name="status"><option value="published">Published</option><option value="draft">Draft</option></select></label>
                        <fieldset class="compact-fieldset"><legend>Accepted responses</legend><div class="check-grid">@foreach (['text','image','pdf','document','spreadsheet','audio','video','file'] as $type)<label class="check-row"><input name="allowed_submission_types[]" type="checkbox" value="{{ $type }}" @checked($type === 'text')><span>{{ str($type)->headline() }}</span></label>@endforeach</div></fieldset>
                        <label class="field-group"><span>Maximum uploaded files</span><input name="max_submission_files" type="number" min="1" max="10" value="3"></label>
                        <button class="primary-button" type="submit">Create assignment</button>
                    </form>
                </section>
            </aside>
        </div>
    @elseif ($activeSection === 'attendance')
        <section class="content-section">
            <div class="section-heading"><div><p class="eyebrow">Daily register</p><h2>Class attendance</h2></div><p>Open a class, choose the date and save all student statuses together.</p></div>
            <div class="record-list">
                @forelse ($classes as $class)
                    <details class="record-disclosure">
                        <summary><span><strong>{{ $class->display_name }}</strong><small>{{ $studentsByClass->get($class->id, collect())->count() }} current students</small></span><span>Open register</span></summary>
                        <form method="POST" action="{{ route($routePrefix.'.teacher.learning.attendance.store') }}" class="attendance-form">
                            @csrf
                            <input type="hidden" name="school_class_id" value="{{ $class->id }}">
                            <label class="field-group attendance-date"><span>Attendance date</span><input name="attendance_date" type="date" value="{{ now()->format('Y-m-d') }}" required></label>
                            <div class="data-table-wrap">
                                <table class="data-table">
                                    <thead><tr><th>Student</th><th>Admission</th><th>Status</th><th>Note</th></tr></thead>
                                    <tbody>
                                        @forelse ($studentsByClass->get($class->id, collect()) as $attendanceStudent)
                                            <tr>
                                                <td data-label="Student"><strong>{{ $attendanceStudent->user->fullName() }}</strong></td>
                                                <td data-label="Admission">{{ $attendanceStudent->admission_no }}</td>
                                                <td data-label="Status"><select name="records[{{ $attendanceStudent->id }}][status]" required>@foreach (['present','late','absent','excused'] as $status)<option value="{{ $status }}">{{ str($status)->headline() }}</option>@endforeach</select></td>
                                                <td data-label="Note"><input name="records[{{ $attendanceStudent->id }}][note]" maxlength="1000"></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4">No students are currently assigned to this class.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if ($studentsByClass->get($class->id, collect())->isNotEmpty())<div class="form-actions"><button class="primary-button" type="submit">Save attendance</button></div>@endif
                        </form>
                    </details>
                @empty
                    <div class="empty-state">No accessible classes are available.</div>
                @endforelse
            </div>
        </section>
    @elseif ($activeSection === 'results')
        <div class="split-workspace">
            <section class="workspace-primary">
                <div class="section-heading compact-heading"><div><p class="eyebrow">Ordinary assessments</p><h2>Assessments</h2></div></div>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Assessment</th><th>Term</th><th>Class</th><th>Subject</th><th>Type</th><th>Maximum</th></tr></thead>
                        <tbody>
                            @forelse ($ordinaryAssessments as $assessment)
                                <tr><td><strong>{{ $assessment->title }}</strong></td><td>{{ $assessment->term?->name }}</td><td>{{ $assessment->schoolClass->display_name }}</td><td>{{ $assessment->subject->name }}</td><td>{{ $assessment->type->label() }}</td><td>{{ number_format((float) $assessment->total_score, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="6">No ordinary assessments are available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <aside class="workspace-aside">
                <section class="plain-panel">
                    <div class="section-heading compact-heading"><div><p class="eyebrow">Assessment setup</p><h2>Create assessment</h2></div></div>
                    <form method="POST" action="{{ route($routePrefix.'.teacher.learning.assessments.store') }}" class="form-stack">
                        @csrf
                        <label class="field-group"><span>Term</span><select name="term_id" required><option value="">Select term</option>@foreach ($terms as $term)<option value="{{ $term->id }}">{{ $term->academicSession?->name }} · {{ $term->name }}</option>@endforeach</select></label>
                        <label class="field-group"><span>Class</span><select name="school_class_id" required><option value="">Select class</option>@foreach ($classes as $class)<option value="{{ $class->id }}">{{ $class->display_name }}</option>@endforeach</select></label>
                        <label class="field-group"><span>Subject</span><select name="subject_id" required><option value="">Select subject</option>@foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label>
                        <label class="field-group"><span>Title</span><input name="title" required></label>
                        <label class="field-group"><span>Type</span><select name="type" required>@foreach (['quiz','test','project','exam'] as $type)<option value="{{ $type }}">{{ str($type)->headline() }}</option>@endforeach</select></label>
                        <label class="field-group"><span>Total score</span><input name="total_score" type="number" min="1" step="0.01" required></label>
                        <label class="field-group"><span>Scheduled date/time</span><input name="scheduled_at" type="datetime-local"></label>
                        <label class="field-group"><span>Notes</span><textarea name="notes" rows="3"></textarea></label>
                        <button class="primary-button" type="submit">Create assessment</button>
                    </form>
                </section>
                <section class="plain-panel">
                    <div class="section-heading compact-heading"><div><p class="eyebrow">Score entry</p><h2>Record student result</h2></div></div>
                    <form method="POST" action="{{ route($routePrefix.'.teacher.learning.results.store') }}" class="form-stack">
                        @csrf
                        <label class="field-group"><span>Assessment</span><select name="assessment_id" required><option value="">Select assessment</option>@foreach ($ordinaryAssessments as $assessment)<option value="{{ $assessment->id }}">{{ $assessment->title }} · {{ $assessment->schoolClass->display_name }}</option>@endforeach</select></label>
                        <label class="field-group"><span>Student</span><select name="student_id" required><option value="">Select student</option>@foreach ($students as $resultStudent)<option value="{{ $resultStudent->id }}">{{ $resultStudent->user->fullName() }} · {{ $resultStudent->schoolClass?->display_name }}</option>@endforeach</select></label>
                        <label class="field-group"><span>Score</span><input name="score" type="number" min="0" step="0.01" required></label>
                        <label class="field-group"><span>Optional grade override</span><input name="grade" maxlength="10"></label>
                        <label class="field-group"><span>Optional remark override</span><textarea name="remark" rows="2"></textarea></label>
                        <button class="secondary-button" type="submit">Save result</button>
                    </form>
                </section>
            </aside>
        </div>
    @elseif ($activeSection === 'submissions')
        <section class="content-section">
            <div class="section-heading"><div><p class="eyebrow">Submitted work</p><h2>Grade assignments</h2></div><p>Review typed responses and privately stored files before entering a score and feedback.</p></div>
            <div class="record-list">
                @forelse ($submissions as $submission)
                    <details class="record-disclosure" @if ($submission->score === null) open @endif>
                        <summary><span><strong>{{ $submission->student->user->fullName() }} · {{ $submission->assignment->title }}</strong><small>{{ $submission->assignment->subject->name }} · Submitted {{ $submission->submitted_at?->format('d M Y H:i') }}</small></span><span>{{ $submission->score !== null ? number_format((float) $submission->score, 2).' / '.number_format((float) $submission->assignment->total_score, 2) : 'Pending' }}</span></summary>
                        <div class="submission-review">
                            @if ($submission->content)<div class="submitted-text"><h3>Typed response</h3><p>{{ $submission->content }}</p></div>@endif
                            @if (count($submission->attachment_paths ?? []) > 0)
                                <div class="file-link-row">@foreach ($submission->attachment_paths as $index => $path)<a class="secondary-link" href="{{ route($routePrefix.'.private-learning-media.submissions.files', [$submission, $index]) }}">Open file {{ $index + 1 }}</a>@endforeach</div>
                            @endif
                            <form method="POST" action="{{ route($routePrefix.'.teacher.learning.submissions.grade', $submission) }}" class="form-grid form-grid-2">
                                @csrf
                                @method('PATCH')
                                <label class="field-group"><span>Score out of {{ number_format((float) $submission->assignment->total_score, 2) }}</span><input name="score" type="number" min="0" max="{{ $submission->assignment->total_score }}" step="0.01" value="{{ $submission->score }}" required></label>
                                <label class="field-group"><span>Feedback</span><textarea name="feedback" rows="3">{{ $submission->feedback }}</textarea></label>
                                <div class="form-actions form-span-full"><button class="primary-button" type="submit">Save grade</button></div>
                            </form>
                        </div>
                    </details>
                @empty
                    <div class="empty-state">No assignment submissions are available.</div>
                @endforelse
            </div>
        </section>
    @endif
@endsection
