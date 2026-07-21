@extends('layouts.portal')

@section('title', 'Dashboard')

@section('content')
    @php
        $enabledPermissions = collect($permissions)->filter();
    @endphp

    <header class="page-heading">
        <div>
            <p class="eyebrow">Welcome back</p>
            <h1>{{ $user->fullName() }}</h1>
            <p>Your dashboard will display only the school tools and records permitted for your role.</p>
        </div>
    </header>

    <section class="metric-row" aria-label="Account summary">
        <article class="metric-item">
            <span>Account status</span>
            <strong>{{ str($user->status)->headline() }}</strong>
        </article>
        <article class="metric-item">
            <span>Role</span>
            <strong>{{ $user->roleLabel() }}</strong>
        </article>
        <article class="metric-item">
            <span>Available capabilities</span>
            <strong>{{ $enabledPermissions->count() }}</strong>
        </article>
    </section>

    <section class="content-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Authorization</p>
                <h2>Enabled capabilities</h2>
            </div>
            <p>These values are enforced by the Laravel backend and will control the modules shown in this workspace.</p>
        </div>

        <div class="permission-list">
            @forelse ($enabledPermissions as $permission => $allowed)
                <span>{{ str($permission)->after('.')->replace('_', ' ')->headline() }}</span>
            @empty
                <p>No operational capabilities have been assigned to this account yet.</p>
            @endforelse
        </div>
    </section>
@endsection
