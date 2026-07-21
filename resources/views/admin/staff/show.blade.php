@extends('layouts.portal')

@section('title', $staff->user->fullName())

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">Staff record</p>
            <h1>{{ $staff->user->fullName() }}</h1>
            <p>{{ $staff->employee_no }} · {{ $staff->user->roleLabel() }} · {{ $staff->department ?: 'General department' }}</p>
        </div>
        <a class="secondary-link" href="{{ route('web.admin.staff.index') }}">Back to staff</a>
    </header>

    <section class="identity-strip" aria-label="Staff identity summary">
        <div><span>Status</span><strong>{{ $staff->archived_at ? 'Archived' : str($staff->status)->headline() }}</strong></div>
        <div><span>Designation</span><strong>{{ $staff->designation ?: 'Not specified' }}</strong></div>
        <div><span>Qualification</span><strong>{{ $staff->qualification ?: 'Not specified' }}</strong></div>
        <div><span>Monthly salary</span><strong>₦{{ number_format((float) $staff->salary, 2) }}</strong></div>
    </section>

    <nav class="anchor-navigation" aria-label="Staff record sections">
        <a href="#profile">Profile</a>
        <a href="#allocations">Class Allocations</a>
        <a href="#account">Account</a>
    </nav>

    <section class="content-section" id="profile">
        <div class="section-heading">
            <div><p class="eyebrow">Employment profile</p><h2>Staff information</h2></div>
            <p>Identity, role, department, salary and employment details are managed as one transactional record.</p>
        </div>
        <form method="POST" action="{{ route('web.admin.staff.update', $staff) }}" enctype="multipart/form-data" class="long-form">
            @csrf
            @method('PATCH')
            @include('admin.staff._form', ['staff' => $staff])
            <div class="form-actions"><button class="primary-button" type="submit">Save staff record</button></div>
        </form>
    </section>

    <section class="content-section" id="allocations">
        <div class="section-heading">
            <div><p class="eyebrow">Academic responsibility</p><h2>Class-teacher allocations</h2></div>
            <p>Subject-level teaching permissions are managed separately from class-teacher responsibility.</p>
        </div>
        <div class="record-list">
            @forelse ($staff->user->managedClasses as $class)
                <article class="record-row">
                    <div><strong>{{ $class->display_name }}</strong><span>{{ $class->room ?: 'Room not assigned' }}</span></div>
                    <div><span>Capacity</span><strong>{{ $class->capacity ?: '—' }}</strong></div>
                </article>
            @empty
                <div class="empty-state">This staff member is not currently assigned as a class teacher.</div>
            @endforelse
        </div>
        @if (auth()->user()->hasPermission('academics.manage_teacher_access'))
            <div class="section-action"><a class="secondary-link" href="{{ route('web.admin.teacher-access.index') }}">Manage subject permissions</a></div>
        @endif
    </section>

    <section class="content-section danger-zone" id="account">
        <div class="section-heading">
            <div><p class="eyebrow">Account controls</p><h2>Access and archival</h2></div>
            <p>Temporary passwords are displayed once. Archival is reversible and preserves employment and academic history.</p>
        </div>
        <div class="inline-actions">
            <form method="POST" action="{{ route('web.admin.staff.password.reset', $staff) }}">
                @csrf
                <button class="secondary-button" type="submit">Generate temporary password</button>
            </form>

            @if ($staff->archived_at)
                <form method="POST" action="{{ route('web.admin.staff.restore', $staff) }}">
                    @csrf
                    @method('PATCH')
                    <button class="primary-button" type="submit">Restore staff record</button>
                </form>
            @else
                <form method="POST" action="{{ route('web.admin.staff.archive', $staff) }}" class="archive-form">
                    @csrf
                    @method('PATCH')
                    <label class="field-group">
                        <span>Archival reason</span>
                        <input name="reason" required minlength="5" maxlength="500" placeholder="Resignation, retirement or another documented reason">
                    </label>
                    <button class="danger-button" type="submit">Archive staff record</button>
                </form>
            @endif
        </div>
    </section>
@endsection
