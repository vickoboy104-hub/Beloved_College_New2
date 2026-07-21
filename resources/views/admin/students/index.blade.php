@extends('layouts.portal')

@section('title', 'Students')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">Student office</p>
            <h1>Students</h1>
            <p>Register, search, review billing, manage sibling families and preserve archived student records.</p>
        </div>
        <a class="primary-link" href="#register-student">Register student</a>
    </header>

    <section class="metric-row metric-row-5" aria-label="Student summary">
        <article class="metric-item"><span>Current records</span><strong>{{ number_format($stats['total']) }}</strong></article>
        <article class="metric-item"><span>Active</span><strong>{{ number_format($stats['active']) }}</strong></article>
        <article class="metric-item"><span>New students</span><strong>{{ number_format($stats['new']) }}</strong></article>
        <article class="metric-item"><span>Debtors</span><strong>{{ number_format($stats['debtors']) }}</strong></article>
        <article class="metric-item"><span>Outstanding</span><strong>₦{{ number_format($stats['outstanding'], 2) }}</strong></article>
    </section>

    <nav class="workspace-tabs" aria-label="Student office sections">
        @foreach ([
            'directory' => 'Directory',
            'new' => 'New Students',
            'inactive' => 'Inactive',
            'siblings' => 'Sibling Families',
            'debtors' => 'Debtors',
            'class-billing' => 'Class Billing',
            'archived' => 'Archived',
        ] as $key => $label)
            <a
                href="{{ route('web.admin.students.index', ['view' => $key]) }}"
                @class(['is-active' => $view === $key])
                @if ($view === $key) aria-current="page" @endif
            >{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('web.admin.students.index') }}" class="filter-bar">
        <input type="hidden" name="view" value="{{ $view }}">
        <label class="field-group filter-grow">
            <span>Search students</span>
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, admission number, parent, phone or class">
        </label>
        <label class="field-group">
            <span>Class</span>
            <select name="class_id">
                <option value="">All classes</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected((string) ($filters['class_id'] ?? '') === (string) $class->id)>{{ $class->display_name }}</option>
                @endforeach
            </select>
        </label>
        <button class="secondary-button" type="submit">Apply filters</button>
        <a class="text-link" href="{{ route('web.admin.students.index', ['view' => $view]) }}">Reset</a>
    </form>

    @if ($view === 'siblings')
        <section class="record-list" aria-label="Sibling families">
            @forelse ($sibling_rows as $row)
                <article class="record-row record-row-family">
                    <div>
                        <strong>{{ $row['parent']?->fullName() ?? 'Parent account unavailable' }}</strong>
                        <span>{{ $row['parent']?->email ?: $row['parent']?->phone ?: 'No parent contact' }}</span>
                    </div>
                    <div>
                        <span>{{ $row['family_size'] }} linked students</span>
                        <small>{{ $row['class_names']->implode(', ') }}</small>
                    </div>
                    <div class="record-row-stack">
                        @foreach ($row['students'] as $familyStudent)
                            <a href="{{ route('web.admin.students.show', $familyStudent) }}">{{ $familyStudent->user->fullName() }} · {{ $familyStudent->admission_no }}</a>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="empty-state">No sibling families match the selected filters.</div>
            @endforelse
        </section>
    @elseif ($view === 'class-billing')
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Students</th>
                        <th>Debtors</th>
                        <th>Expected</th>
                        <th>Collected</th>
                        <th>Outstanding</th>
                        <th>Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($class_billing_rows as $row)
                        <tr>
                            <td><strong>{{ $row['class']->display_name }}</strong></td>
                            <td>{{ number_format($row['student_count']) }}</td>
                            <td>{{ number_format($row['students_with_debt']) }}</td>
                            <td>₦{{ number_format($row['expected_total'], 2) }}</td>
                            <td>₦{{ number_format($row['collected_total'], 2) }}</td>
                            <td>₦{{ number_format($row['outstanding_total'], 2) }}</td>
                            <td>{{ number_format($row['collection_rate'], 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No class billing records are available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Admission</th>
                        <th>Class</th>
                        <th>Parent/Guardian</th>
                        <th>Status</th>
                        <th>Outstanding</th>
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td data-label="Student">
                                <strong>{{ $student->user->fullName() }}</strong>
                                <small>{{ $student->user->email ?: $student->user->phone ?: 'No direct contact' }}</small>
                            </td>
                            <td data-label="Admission">{{ $student->admission_no }}<small>{{ $student->student_id_no }}</small></td>
                            <td data-label="Class">{{ $student->schoolClass?->display_name ?? 'Unassigned' }}</td>
                            <td data-label="Parent/Guardian">
                                {{ $student->parent?->fullName() ?? $student->guardian_name ?? 'Not linked' }}
                                <small>{{ $student->parent?->phone ?? $student->guardian_phone }}</small>
                            </td>
                            <td data-label="Status"><span class="status-badge status-{{ $student->archived_at ? 'archived' : $student->status }}">{{ $student->archived_at ? 'Archived' : str($student->status)->headline() }}</span></td>
                            <td data-label="Outstanding">₦{{ number_format((float) $student->outstanding_balance, 2) }}</td>
                            <td class="table-actions"><a href="{{ route('web.admin.students.show', $student) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No students match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">{{ $students->links() }}</div>
    @endif

    <details class="action-panel" id="register-student" @if ($errors->any()) open @endif>
        <summary>
            <span>
                <strong>Register a student</strong>
                <small>Complete identity, parent, academic and health information.</small>
            </span>
            <span aria-hidden="true">+</span>
        </summary>
        <form method="POST" action="{{ route('web.admin.students.store') }}" enctype="multipart/form-data" class="long-form">
            @csrf
            @include('admin.students._form', ['student' => null, 'classes' => $classes])
            <div class="form-actions">
                <button class="primary-button" type="submit">Register student</button>
            </div>
        </form>
    </details>
@endsection
