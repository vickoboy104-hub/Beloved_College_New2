@extends('layouts.portal')

@section('title', 'Staff')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">Staff office</p>
            <h1>Staff</h1>
            <p>Manage staff accounts, department records, salary summaries, class allocations and reversible archives.</p>
        </div>
        <a class="primary-link" href="#register-staff">Register staff</a>
    </header>

    <section class="metric-row metric-row-5" aria-label="Staff summary">
        <article class="metric-item"><span>Current staff</span><strong>{{ number_format($stats['total']) }}</strong></article>
        <article class="metric-item"><span>Active</span><strong>{{ number_format($stats['active']) }}</strong></article>
        <article class="metric-item"><span>Class teachers</span><strong>{{ number_format($stats['class_teachers']) }}</strong></article>
        <article class="metric-item"><span>Salary records</span><strong>{{ number_format($stats['salary_count']) }}</strong></article>
        <article class="metric-item"><span>Monthly total</span><strong>₦{{ number_format($stats['monthly_total'], 2) }}</strong></article>
    </section>

    <nav class="workspace-tabs" aria-label="Staff office sections">
        @foreach ([
            'directory' => 'Directory',
            'payroll' => 'Payroll Summary',
            'class-allocation' => 'Class Allocation',
            'archived' => 'Archived',
        ] as $key => $label)
            <a
                href="{{ route('web.admin.staff.index', ['view' => $key]) }}"
                @class(['is-active' => $view === $key])
                @if ($view === $key) aria-current="page" @endif
            >{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('web.admin.staff.index') }}" class="filter-bar">
        <input type="hidden" name="view" value="{{ $view }}">
        <label class="field-group filter-grow">
            <span>Search staff</span>
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, employee number, email, role or designation">
        </label>
        <label class="field-group">
            <span>Department</span>
            <select name="department">
                <option value="">All departments</option>
                @foreach ($departments as $department)
                    <option value="{{ $department }}" @selected(($filters['department'] ?? '') === $department)>{{ $department }}</option>
                @endforeach
            </select>
        </label>
        <button class="secondary-button" type="submit">Apply filters</button>
        <a class="text-link" href="{{ route('web.admin.staff.index', ['view' => $view]) }}">Reset</a>
    </form>

    @if ($view === 'payroll')
        <div class="data-table-wrap">
            <table class="data-table">
                <thead><tr><th>Department</th><th>Staff</th><th>Salary records</th><th>Monthly total</th><th>Average salary</th></tr></thead>
                <tbody>
                    @forelse ($payroll_rows as $row)
                        <tr>
                            <td><strong>{{ $row['department'] }}</strong></td>
                            <td>{{ number_format($row['staff_count']) }}</td>
                            <td>{{ number_format($row['staff_with_salary']) }}</td>
                            <td>₦{{ number_format($row['monthly_total'], 2) }}</td>
                            <td>₦{{ number_format($row['average_salary'], 2) }}</td>
                        </tr>
                        @foreach ($row['profiles'] as $profile)
                            <tr class="sub-row">
                                <td colspan="2"><a href="{{ route('web.admin.staff.show', $profile) }}">{{ $profile->user->fullName() }}</a><small>{{ $profile->employee_no }}</small></td>
                                <td>{{ $profile->designation ?: $profile->user->roleLabel() }}</td>
                                <td>₦{{ number_format((float) $profile->salary, 2) }}</td>
                                <td>{{ $profile->status }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="5">No salary records match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($view === 'class-allocation')
        <div class="data-table-wrap">
            <table class="data-table">
                <thead><tr><th>Class</th><th>Class teacher</th><th>Department</th><th>Designation</th></tr></thead>
                <tbody>
                    @forelse ($class_allocation_rows as $row)
                        <tr>
                            <td><strong>{{ $row['class']->display_name }}</strong></td>
                            <td>{{ $row['teacher']?->fullName() ?? 'Not assigned' }}</td>
                            <td>{{ $row['department'] ?: '—' }}</td>
                            <td>{{ $row['designation'] ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No classes have been created.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="data-table-wrap">
            <table class="data-table">
                <thead><tr><th>Staff member</th><th>Employee number</th><th>Role</th><th>Department</th><th>Status</th><th>Classes</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($staff as $profile)
                        <tr>
                            <td data-label="Staff member"><strong>{{ $profile->user->fullName() }}</strong><small>{{ $profile->user->email }}</small></td>
                            <td data-label="Employee number">{{ $profile->employee_no }}</td>
                            <td data-label="Role">{{ $profile->user->roleLabel() }}</td>
                            <td data-label="Department">{{ $profile->department ?: 'General' }}<small>{{ $profile->designation }}</small></td>
                            <td data-label="Status"><span class="status-badge status-{{ $profile->archived_at ? 'archived' : $profile->status }}">{{ $profile->archived_at ? 'Archived' : str($profile->status)->headline() }}</span></td>
                            <td data-label="Classes">{{ $profile->user->managedClasses->pluck('display_name')->implode(', ') ?: '—' }}</td>
                            <td class="table-actions"><a href="{{ route('web.admin.staff.show', $profile) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No staff records match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $staff->links() }}</div>
    @endif

    <details class="action-panel" id="register-staff" @if ($errors->any()) open @endif>
        <summary>
            <span><strong>Register a staff member</strong><small>Create the account and employment profile in one transaction.</small></span>
            <span aria-hidden="true">+</span>
        </summary>
        <form method="POST" action="{{ route('web.admin.staff.store') }}" enctype="multipart/form-data" class="long-form">
            @csrf
            @include('admin.staff._form', ['staff' => null])
            <div class="form-actions"><button class="primary-button" type="submit">Create staff account</button></div>
        </form>
    </details>
@endsection
