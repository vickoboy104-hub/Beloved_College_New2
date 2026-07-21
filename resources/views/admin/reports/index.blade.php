@extends('layouts.portal')

@section('title', 'Reports')

@section('content')
    <header class="page-heading">
        <div>
            <p class="eyebrow">Academic reporting</p>
            <h1>Student Reports</h1>
            <p>Compile subject scores, review class positions, complete remarks and control private or public publication.</p>
        </div>
    </header>

    <form method="GET" action="{{ route('web.admin.reports.index') }}" class="filter-bar">
        <label class="field-group filter-grow"><span>Term</span><select name="term_id"><option value="">Current term</option>@foreach ($terms as $term)<option value="{{ $term->id }}" @selected($selectedTerm?->id === $term->id)>{{ $term->academicSession?->name }} · {{ $term->name }}</option>@endforeach</select></label>
        <label class="field-group"><span>Class</span><select name="class_id"><option value="">All classes</option>@foreach ($classes as $class)<option value="{{ $class->id }}" @selected($selectedClass?->id === $class->id)>{{ $class->display_name }}</option>@endforeach</select></label>
        <button class="secondary-button" type="submit">Apply filters</button>
    </form>

    @if ($selectedTerm && $selectedClass)
        <section class="control-strip">
            <div><strong>Compile {{ $selectedClass->display_name }}</strong><span>Rebuild every student report from the current assessment results for {{ $selectedTerm->academicSession?->name }} {{ $selectedTerm->name }}.</span></div>
            <form method="POST" action="{{ route('web.admin.reports.compile-class') }}">@csrf<input type="hidden" name="term_id" value="{{ $selectedTerm->id }}"><input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}"><button class="primary-button" type="submit">Compile class reports</button></form>
        </section>
    @endif

    <div class="data-table-wrap">
        <table class="data-table">
            <thead><tr><th>Student</th><th>Term</th><th>Class</th><th>Average</th><th>Grade</th><th>Position</th><th>Private portal</th><th>Public checker</th><th><span class="sr-only">Action</span></th></tr></thead>
            <tbody>
                @forelse ($reports as $report)
                    <tr>
                        <td><strong>{{ $report->student->user->fullName() }}</strong><small>{{ $report->student->admission_no }}</small></td>
                        <td>{{ $report->term->academicSession?->name }}<small>{{ $report->term->name }}</small></td>
                        <td>{{ $report->student->schoolClass?->display_name ?? 'Unassigned' }}</td>
                        <td>{{ number_format((float) $report->average_score, 2) }}%</td>
                        <td>{{ $report->overall_grade ?: '—' }}</td>
                        <td>{{ $report->class_position ?: '—' }}</td>
                        <td><span class="status-badge status-{{ $report->portal_enabled ? 'active' : 'inactive' }}">{{ $report->portal_enabled ? 'Enabled' : 'Disabled' }}</span></td>
                        <td><span class="status-badge status-{{ $report->checker_enabled ? 'active' : 'inactive' }}">{{ $report->checker_enabled ? 'Enabled' : 'Disabled' }}</span></td>
                        <td class="table-actions"><a href="{{ route('web.admin.reports.show', $report) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9">No compiled reports match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">{{ $reports->links() }}</div>

    @if ($selectedTerm && $selectedClass && $studentsWithoutReports->isNotEmpty())
        <details class="action-panel">
            <summary><span><strong>Students without compiled reports</strong><small>{{ $studentsWithoutReports->count() }} records can be compiled individually.</small></span><span aria-hidden="true">+</span></summary>
            <div class="record-list">
                @foreach ($studentsWithoutReports as $student)
                    <article class="record-row"><div><strong>{{ $student->user->fullName() }}</strong><span>{{ $student->admission_no }}</span></div><div class="record-row-stack"><form method="POST" action="{{ route('web.admin.reports.compile', $student) }}">@csrf<input type="hidden" name="term_id" value="{{ $selectedTerm->id }}"><button class="text-button" type="submit">Compile report</button></form></div></article>
                @endforeach
            </div>
        </details>
    @endif
@endsection
