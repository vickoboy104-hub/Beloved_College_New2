@extends('layouts.portal')

@php $surfacePrefix = $surface === \App\Enums\PortalSurface::AppPortal ? 'app' : 'web'; @endphp

@section('title', 'Account Security')

@section('content')
    <header class="page-heading">
        <p class="eyebrow">Identity and access</p>
        <h1>Account Security</h1>
        <p>Review email verification, change your password, inspect active sessions and check recent security activity.</p>
    </header>

    <section class="security-status-strip">
        <article><span>Email</span><strong>{{ auth()->user()->email ?: 'Not assigned' }}</strong><small>{{ auth()->user()->hasVerifiedEmail() ? 'Verified' : (auth()->user()->email ? 'Not verified' : 'Contact school office') }}</small></article>
        <article><span>Password changed</span><strong>{{ auth()->user()->password_changed_at?->format('d M Y') ?: 'Not recorded' }}</strong><small>{{ auth()->user()->must_change_password ? 'Temporary password must be replaced' : 'Password active' }}</small></article>
        <article><span>Last sign-in</span><strong>{{ auth()->user()->last_login_at?->format('d M Y H:i') ?: 'Not recorded' }}</strong><small>{{ auth()->user()->last_login_ip ?: 'IP unavailable' }}</small></article>
        <article><span>Verification policy</span><strong>{{ $emailVerificationRequired ? 'Required' : 'Optional' }}</strong><small>Users without email are not blocked</small></article>
    </section>

    <div class="security-workspace-grid">
        <section class="security-primary-column">
            <article class="content-section">
                <div class="section-heading"><div><p class="eyebrow">Password</p><h2>Change password</h2></div><p>Changing your password signs out every other active session.</p></div>
                <form method="POST" action="{{ route($surfacePrefix.'.security.password.update') }}" class="form-grid form-grid-2">@csrf @method('PUT')<label class="field-group form-span-full"><span>Current password</span><input name="current_password" type="password" autocomplete="current-password" required></label><label class="field-group"><span>New password</span><input name="password" type="password" autocomplete="new-password" required><small>At least 10 characters, uppercase, lowercase and a number.</small></label><label class="field-group"><span>Confirm new password</span><input name="password_confirmation" type="password" autocomplete="new-password" required></label><div class="form-actions form-span-full"><button class="primary-button" type="submit">Update password</button></div></form>
            </article>

            <article class="content-section">
                <div class="section-heading"><div><p class="eyebrow">Sessions</p><h2>Active devices</h2></div><p>Session records use the shared database session store for both portal subdomains.</p></div>
                <div class="security-session-list">
                    @forelse($sessions as $session)
                        <article @class(['is-current' => $session['is_current']])><div><strong>{{ $session['device'] }} · {{ $session['browser'] }}</strong><span>{{ $session['ip_address'] ?: 'Unknown IP' }} · Active {{ $session['last_activity']->diffForHumans() }}</span></div><div>@if($session['is_current'])<span class="status-badge status-active">Current session</span>@else<form method="POST" action="{{ route($surfacePrefix.'.security.sessions.destroy', $session['id']) }}">@csrf @method('DELETE')<button class="text-button" type="submit">Sign out device</button></form>@endif</div></article>
                    @empty
                        <div class="empty-state">No database session records are available.</div>
                    @endforelse
                </div>
                @if($sessions->where('is_current', false)->isNotEmpty())
                    <form method="POST" action="{{ route($surfacePrefix.'.security.sessions.destroy-others') }}" class="security-revoke-all-form">@csrf @method('DELETE')<label class="field-group"><span>Current password</span><input name="current_password" type="password" autocomplete="current-password" required></label><button class="secondary-button" type="submit">Sign out all other devices</button></form>
                @endif
            </article>
        </section>

        <aside class="security-aside-column">
            <section class="plain-panel">
                <div class="section-heading compact-heading"><div><p class="eyebrow">Email</p><h2>Verification</h2></div></div>
                @if(blank(auth()->user()->email))
                    <p class="security-help-copy">No email address is assigned. Contact an authorized school administrator to add or correct the account email.</p>
                @elseif(auth()->user()->hasVerifiedEmail())
                    <div class="notice notice-success">Your email address is verified.</div>
                @else
                    <p class="security-help-copy">Verification strengthens password recovery and account-security alerts.</p>
                    <form method="POST" action="{{ route($surfacePrefix.'.verification.send') }}">@csrf<button class="secondary-button" type="submit">Send verification link</button></form>
                @endif
            </section>

            <section class="plain-panel">
                <div class="section-heading compact-heading"><div><p class="eyebrow">Recent history</p><h2>Security events</h2></div></div>
                <div class="security-event-list">
                    @forelse($securityEvents as $event)
                        <article><span class="security-event-marker severity-{{ $event->severity }}"></span><div><strong>{{ str($event->event)->replace('.', ' ')->headline() }}</strong><span>{{ $event->occurred_at->format('d M Y H:i:s') }} · {{ $event->ip_address ?: 'IP unavailable' }}</span></div></article>
                    @empty
                        <div class="empty-state">No security events are recorded yet.</div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
@endsection
