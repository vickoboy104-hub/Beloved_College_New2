@extends('layouts.portal')

@section('title', 'Teacher Access')

@section('content')
    <header class="page-heading">
        <div>
            <p class="eyebrow">Academic authorization</p>
            <h1>Teacher Access</h1>
            <p>Grant exact teacher, class and subject combinations. Revocation removes access without deleting its history.</p>
        </div>
    </header>

    <section class="metric-row" aria-label="Teacher access summary">
        <article class="metric-item"><span>Eligible teachers</span><strong>{{ number_format($teachers->count()) }}</strong></article>
        <article class="metric-item"><span>Active assignments</span><strong>{{ number_format($activeAssignments->total()) }}</strong></article>
        <article class="metric-item"><span>Revoked assignments</span><strong>{{ number_format($revokedAssignments->total()) }}</strong></article>
    </section>

    <div class="split-workspace">
        <section class="workspace-primary">
            <div class="section-heading compact-heading">
                <div><p class="eyebrow">Active permissions</p><h2>Teacher–class–subject assignments</h2></div>
            </div>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Teacher</th><th>Class</th><th>Subject</th><th>Assigned</th><th>Assigned by</th><th><span class="sr-only">Action</span></th></tr></thead>
                    <tbody>
                        @forelse ($activeAssignments as $assignment)
                            <tr>
                                <td><strong>{{ $assignment->teacher->fullName() }}</strong><small>{{ $assignment->teacher->roleLabel() }}</small></td>
                                <td>{{ $assignment->schoolClass->display_name }}</td>
                                <td>{{ $assignment->subject->name }}<small>{{ $assignment->subject->code }}</small></td>
                                <td>{{ $assignment->assigned_at?->format('d M Y H:i') ?: 'Not recorded' }}</td>
                                <td>{{ $assignment->assignedByUser?->fullName() ?? 'System' }}</td>
                                <td class="table-actions">
                                    <form method="POST" action="{{ route('web.admin.teacher-access.revoke', $assignment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="text-button danger-text" type="submit">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No active teacher access assignments are available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $activeAssignments->links() }}</div>

            <details class="action-panel subtle-panel">
                <summary>
                    <span><strong>Revoked access history</strong><small>Restore an assignment without recreating its record.</small></span>
                    <span aria-hidden="true">+</span>
                </summary>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Teacher</th><th>Class</th><th>Subject</th><th>Revoked</th><th>Revoked by</th><th><span class="sr-only">Action</span></th></tr></thead>
                        <tbody>
                            @forelse ($revokedAssignments as $assignment)
                                <tr>
                                    <td>{{ $assignment->teacher->fullName() }}</td>
                                    <td>{{ $assignment->schoolClass->display_name }}</td>
                                    <td>{{ $assignment->subject->name }}</td>
                                    <td>{{ $assignment->revoked_at?->format('d M Y H:i') }}</td>
                                    <td>{{ $assignment->revokedByUser?->fullName() ?? 'System' }}</td>
                                    <td class="table-actions">
                                        <form method="POST" action="{{ route('web.admin.teacher-access.restore', $assignment) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="text-button" type="submit">Restore</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No revoked assignments are available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-wrap">{{ $revokedAssignments->links() }}</div>
            </details>
        </section>

        <aside class="workspace-aside">
            <section class="plain-panel">
                <div class="section-heading compact-heading">
                    <div><p class="eyebrow">Single assignment</p><h2>Grant access</h2></div>
                </div>
                <form method="POST" action="{{ route('web.admin.teacher-access.store') }}" class="form-stack">
                    @csrf
                    <label class="field-group"><span>Teacher</span><select name="teacher_id" required><option value="">Select teacher</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->fullName() }} · {{ $teacher->roleLabel() }}</option>@endforeach</select></label>
                    <label class="field-group"><span>Class</span><select name="school_class_id" required><option value="">Select class</option>@foreach ($classes as $class)<option value="{{ $class->id }}">{{ $class->display_name }}</option>@endforeach</select></label>
                    <label class="field-group"><span>Subject</span><select name="subject_id" required><option value="">Select subject</option>@foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}{{ $subject->code ? ' · '.$subject->code : '' }}</option>@endforeach</select></label>
                    <button class="primary-button" type="submit">Grant teacher access</button>
                </form>
            </section>

            <section class="plain-panel">
                <div class="section-heading compact-heading">
                    <div><p class="eyebrow">Bulk assignment</p><h2>Assign combinations</h2></div>
                </div>
                <form method="POST" action="{{ route('web.admin.teacher-access.bulk') }}" class="form-stack">
                    @csrf
                    <label class="field-group"><span>Teachers</span><select name="teacher_ids[]" multiple size="6" required>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->fullName() }}</option>@endforeach</select></label>
                    <label class="field-group"><span>Classes</span><select name="school_class_ids[]" multiple size="5" required>@foreach ($classes as $class)<option value="{{ $class->id }}">{{ $class->display_name }}</option>@endforeach</select></label>
                    <label class="field-group"><span>Subjects</span><select name="subject_ids[]" multiple size="7" required>@foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label>
                    <p class="form-help">Every selected teacher will receive each selected class-and-subject combination. Existing combinations are restored instead of duplicated.</p>
                    <button class="secondary-button" type="submit">Assign selected combinations</button>
                </form>
            </section>
        </aside>
    </div>
@endsection
