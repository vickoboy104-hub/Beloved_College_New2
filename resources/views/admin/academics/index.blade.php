@extends('layouts.portal')

@section('title', 'Academics')

@section('content')
    <header class="page-heading">
        <div>
            <p class="eyebrow">Academic administration</p>
            <h1>Academic Setup</h1>
            <p>Manage sessions, terms, classes, subjects and promotion processing without leaving one structured workspace.</p>
        </div>
    </header>

    <section class="metric-row metric-row-5" aria-label="Academic summary">
        <article class="metric-item"><span>Sessions</span><strong>{{ number_format($sessions->count()) }}</strong></article>
        <article class="metric-item"><span>Terms</span><strong>{{ number_format($terms->count()) }}</strong></article>
        <article class="metric-item"><span>Classes</span><strong>{{ number_format($classes->count()) }}</strong></article>
        <article class="metric-item"><span>Subjects</span><strong>{{ number_format($subjects->count()) }}</strong></article>
        <article class="metric-item"><span>Current session</span><strong>{{ $currentSession?->name ?? 'Not set' }}</strong></article>
    </section>

    <nav class="workspace-tabs" aria-label="Academic setup sections">
        @foreach ([
            'sessions' => 'Sessions',
            'terms' => 'Terms',
            'classes' => 'Classes',
            'subjects' => 'Subjects',
            'promotions' => 'Promotions',
        ] as $key => $label)
            <a
                href="{{ route('web.admin.academics.index', ['section' => $key]) }}"
                @class(['is-active' => $activeSection === $key])
                @if ($activeSection === $key) aria-current="page" @endif
            >{{ $label }}</a>
        @endforeach
    </nav>

    @if ($activeSection === 'sessions')
        <div class="split-workspace">
            <section class="workspace-primary">
                <div class="section-heading compact-heading"><div><p class="eyebrow">Academic years</p><h2>Sessions</h2></div></div>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Session</th><th>Dates</th><th>Pass mark</th><th>Status</th><th>Closed by</th><th><span class="sr-only">Action</span></th></tr></thead>
                        <tbody>
                            @forelse ($sessions as $session)
                                <tr>
                                    <td><strong>{{ $session->name }}</strong></td>
                                    <td>{{ $session->start_date->format('d M Y') }} – {{ $session->end_date->format('d M Y') }}</td>
                                    <td>{{ number_format((float) $session->promotion_pass_mark, 2) }}%</td>
                                    <td><span class="status-badge status-{{ $session->closed_at ? 'archived' : ($session->is_current ? 'active' : 'inactive') }}">{{ $session->closed_at ? 'Closed' : ($session->is_current ? 'Current' : 'Open') }}</span></td>
                                    <td>{{ $session->closedByUser?->fullName() ?? '—' }}</td>
                                    <td class="table-actions">
                                        @if (! $session->closed_at)
                                            <details class="inline-disclosure">
                                                <summary>Close</summary>
                                                <form method="POST" action="{{ route('web.admin.academics.sessions.close', $session) }}" class="inline-form">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="field-group"><span>Final pass mark</span><input name="promotion_pass_mark" type="number" min="0" max="100" step="0.01" value="{{ $session->promotion_pass_mark }}" required></label>
                                                    <button class="danger-button" type="submit">Close session</button>
                                                </form>
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No academic sessions have been created.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <aside class="workspace-aside">
                <section class="plain-panel">
                    <div class="section-heading compact-heading"><div><p class="eyebrow">New academic year</p><h2>Create session</h2></div></div>
                    <form method="POST" action="{{ route('web.admin.academics.sessions.store') }}" class="form-stack">
                        @csrf
                        <label class="field-group"><span>Session name</span><input name="name" placeholder="2026/2027" required></label>
                        <label class="field-group"><span>Start date</span><input name="start_date" type="date" required></label>
                        <label class="field-group"><span>End date</span><input name="end_date" type="date" required></label>
                        <label class="field-group"><span>Promotion pass mark</span><input name="promotion_pass_mark" type="number" min="0" max="100" step="0.01" value="50"></label>
                        <label class="check-row"><input name="is_current" type="checkbox" value="1"><span>Make this the current session</span></label>
                        <button class="primary-button" type="submit">Create session</button>
                    </form>
                </section>
            </aside>
        </div>
    @elseif ($activeSection === 'terms')
        <div class="split-workspace">
            <section class="workspace-primary">
                <div class="section-heading compact-heading"><div><p class="eyebrow">Academic periods</p><h2>Terms</h2></div></div>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Term</th><th>Session</th><th>Dates</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($terms as $term)
                                <tr><td><strong>{{ $term->name }}</strong></td><td>{{ $term->academicSession?->name }}</td><td>{{ $term->start_date->format('d M Y') }} – {{ $term->end_date->format('d M Y') }}</td><td><span class="status-badge status-{{ $term->is_current ? 'active' : 'inactive' }}">{{ $term->is_current ? 'Current' : 'Inactive' }}</span></td></tr>
                            @empty
                                <tr><td colspan="4">No terms have been created.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <aside class="workspace-aside">
                <section class="plain-panel">
                    <div class="section-heading compact-heading"><div><p class="eyebrow">New period</p><h2>Create term</h2></div></div>
                    <form method="POST" action="{{ route('web.admin.academics.terms.store') }}" class="form-stack">
                        @csrf
                        <label class="field-group"><span>Academic session</span><select name="academic_session_id" required><option value="">Select session</option>@foreach ($sessions as $session)<option value="{{ $session->id }}">{{ $session->name }}</option>@endforeach</select></label>
                        <label class="field-group"><span>Term name</span><input name="name" placeholder="First Term" required></label>
                        <label class="field-group"><span>Start date</span><input name="start_date" type="date" required></label>
                        <label class="field-group"><span>End date</span><input name="end_date" type="date" required></label>
                        <label class="check-row"><input name="is_current" type="checkbox" value="1"><span>Make this the current term</span></label>
                        <button class="primary-button" type="submit">Create term</button>
                    </form>
                </section>
            </aside>
        </div>
    @elseif ($activeSection === 'classes')
        <div class="split-workspace">
            <section class="workspace-primary">
                <div class="section-heading compact-heading"><div><p class="eyebrow">Class structure</p><h2>Classes and sections</h2></div></div>
                <div class="record-list">
                    @forelse ($classes as $class)
                        <details class="record-disclosure">
                            <summary>
                                <span><strong>{{ $class->display_name }}</strong><small>{{ $class->classTeacher?->fullName() ?? 'No class teacher' }}</small></span>
                                <span>{{ $class->room ?: 'Room not assigned' }}</span>
                            </summary>
                            <form method="POST" action="{{ route('web.admin.academics.classes.update', $class) }}" class="form-grid form-grid-3 inline-edit-form">
                                @csrf
                                @method('PATCH')
                                <label class="field-group"><span>Name</span><input name="name" value="{{ $class->name }}" required></label>
                                <label class="field-group"><span>Section</span><input name="section" value="{{ $class->section }}"></label>
                                <label class="field-group"><span>Class teacher</span><select name="class_teacher_id"><option value="">Not assigned</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($class->class_teacher_id === $teacher->id)>{{ $teacher->fullName() }}</option>@endforeach</select></label>
                                <label class="field-group"><span>Capacity</span><input name="capacity" type="number" min="1" value="{{ $class->capacity }}"></label>
                                <label class="field-group"><span>Room</span><input name="room" value="{{ $class->room }}"></label>
                                <label class="field-group form-span-full"><span>Description</span><textarea name="description" rows="2">{{ $class->description }}</textarea></label>
                                <div class="form-actions form-span-full"><button class="secondary-button" type="submit">Save class</button></div>
                            </form>
                        </details>
                    @empty
                        <div class="empty-state">No classes have been created.</div>
                    @endforelse
                </div>
            </section>
            <aside class="workspace-aside">
                <section class="plain-panel">
                    <div class="section-heading compact-heading"><div><p class="eyebrow">New class</p><h2>Create class</h2></div></div>
                    <form method="POST" action="{{ route('web.admin.academics.classes.store') }}" class="form-stack">
                        @csrf
                        <label class="field-group"><span>Name</span><input name="name" placeholder="JSS 1" required></label>
                        <label class="field-group"><span>Section</span><input name="section" placeholder="A"></label>
                        <label class="field-group"><span>Class teacher</span><select name="class_teacher_id"><option value="">Not assigned</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->fullName() }}</option>@endforeach</select></label>
                        <label class="field-group"><span>Capacity</span><input name="capacity" type="number" min="1"></label>
                        <label class="field-group"><span>Room</span><input name="room"></label>
                        <label class="field-group"><span>Description</span><textarea name="description" rows="3"></textarea></label>
                        <button class="primary-button" type="submit">Create class</button>
                    </form>
                </section>
            </aside>
        </div>
    @elseif ($activeSection === 'subjects')
        <div class="split-workspace">
            <section class="workspace-primary">
                <div class="section-heading compact-heading"><div><p class="eyebrow">Curriculum</p><h2>Subjects</h2></div></div>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Subject</th><th>Code</th><th>Description</th></tr></thead>
                        <tbody>
                            @forelse ($subjects as $subject)
                                <tr><td><strong>{{ $subject->name }}</strong></td><td>{{ $subject->code ?: '—' }}</td><td>{{ $subject->description ?: '—' }}</td></tr>
                            @empty
                                <tr><td colspan="3">No subjects have been created.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <aside class="workspace-aside">
                <section class="plain-panel">
                    <div class="section-heading compact-heading"><div><p class="eyebrow">New curriculum item</p><h2>Create subject</h2></div></div>
                    <form method="POST" action="{{ route('web.admin.academics.subjects.store') }}" class="form-stack">
                        @csrf
                        <label class="field-group"><span>Subject name</span><input name="name" required></label>
                        <label class="field-group"><span>Subject code</span><input name="code"></label>
                        <label class="field-group"><span>Description</span><textarea name="description" rows="4"></textarea></label>
                        <button class="primary-button" type="submit">Create subject</button>
                    </form>
                </section>
            </aside>
        </div>
    @elseif ($activeSection === 'promotions')
        <section class="content-section promotion-workspace">
            <div class="section-heading">
                <div><p class="eyebrow">Session rollover</p><h2>Promotion review</h2></div>
                <p>Recommendations are calculated from subject percentages. Every final decision and target class can be reviewed before processing.</p>
            </div>

            @if (! $sourceSession || ! $currentSession)
                <div class="empty-state">A closed source session and one current target session are required before promotion processing.</div>
            @elseif ($promotionPreview->isEmpty())
                <div class="empty-state">There are no students awaiting processing in {{ $sourceSession->name }}.</div>
            @else
                <form method="POST" action="{{ route('web.admin.academics.promotions.process') }}">
                    @csrf
                    <input type="hidden" name="source_session_id" value="{{ $sourceSession->id }}">
                    <input type="hidden" name="target_session_id" value="{{ $currentSession->id }}">
                    <div class="promotion-context"><span>Source: <strong>{{ $sourceSession->name }}</strong></span><span>Target: <strong>{{ $currentSession->name }}</strong></span><span>Pass mark: <strong>{{ number_format((float) $sourceSession->promotion_pass_mark, 2) }}%</strong></span></div>
                    <div class="data-table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Student</th><th>Current class</th><th>Subjects</th><th>Average</th><th>Recommendation</th><th>Decision</th><th>Target class</th><th>Note</th></tr></thead>
                            <tbody>
                                @foreach ($promotionPreview as $row)
                                    @php $promotionStudent = $row['student']; @endphp
                                    <tr>
                                        <td><strong>{{ $promotionStudent->user->fullName() }}</strong><small>{{ $promotionStudent->admission_no }}</small></td>
                                        <td>{{ $row['current_class']?->display_name ?? 'Unassigned' }}</td>
                                        <td>{{ $row['subject_count'] }}</td>
                                        <td>{{ number_format($row['overall_percentage'], 2) }}%</td>
                                        <td><span class="status-badge status-{{ $row['recommended_status'] === 'promote' ? 'active' : 'inactive' }}">{{ str($row['recommended_status'])->headline() }}</span></td>
                                        <td><select name="decisions[{{ $promotionStudent->id }}]"><option value="promote" @selected($row['recommended_status'] === 'promote')>Promote</option><option value="repeat" @selected($row['recommended_status'] === 'repeat')>Repeat</option></select></td>
                                        <td><select name="target_class_ids[{{ $promotionStudent->id }}]"><option value="">Use recommendation</option>@foreach ($classes as $class)<option value="{{ $class->id }}" @selected($row['recommended_next_class']?->id === $class->id)>{{ $class->display_name }}</option>@endforeach</select></td>
                                        <td><input name="notes[{{ $promotionStudent->id }}]" maxlength="500" placeholder="Optional"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="form-actions"><button class="primary-button" type="submit">Process reviewed promotions</button></div>
                </form>
            @endif
        </section>
    @endif
@endsection
