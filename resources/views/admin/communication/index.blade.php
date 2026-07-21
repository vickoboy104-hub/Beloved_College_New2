@extends('layouts.portal')

@section('title', 'Communication')

@section('content')
    <header class="page-heading">
        <p class="eyebrow">Communication centre</p>
        <h1>Announcements and Alerts</h1>
        <p>Create targeted portal and email messages, schedule delivery, review recipient history and control automatic Parent absence alerts.</p>
    </header>

    <section class="metric-row metric-row-5" aria-label="Communication summary">
        <article class="metric-item"><span>Drafts</span><strong>{{ $counts['drafts'] }}</strong></article>
        <article class="metric-item"><span>Scheduled</span><strong>{{ $counts['scheduled'] }}</strong></article>
        <article class="metric-item"><span>Dispatched</span><strong>{{ $counts['dispatched'] }}</strong></article>
        <article class="metric-item"><span>Delivery records</span><strong>{{ number_format($counts['deliveries']) }}</strong></article>
        <article class="metric-item"><span>Unread</span><strong>{{ number_format($counts['unread']) }}</strong></article>
    </section>

    <nav class="workspace-tabs" aria-label="Communication sections">
        @foreach (['overview' => 'Overview', 'compose' => 'Compose', 'history' => 'Delivery History', 'settings' => 'Settings'] as $key => $label)
            <a href="{{ route('web.admin.communication.index', ['section' => $key]) }}" @class(['is-active' => $activeSection === $key])>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($activeSection === 'overview')
        <section class="communication-overview-list">
            <article><span>01</span><div><h2>Target the right audience</h2><p>Send to every active user or combine selected roles, classes, Students, Parents and staff members.</p></div><a href="{{ route('web.admin.communication.index', ['section' => 'compose']) }}">Compose message</a></article>
            <article><span>02</span><div><h2>Portal and queued email</h2><p>Database notifications appear immediately in each user's inbox. Email delivery runs on the notification queue when enabled.</p></div><a href="{{ route('web.admin.system.index', ['section' => 'mail']) }}">Review mail settings</a></article>
            <article><span>03</span><div><h2>Scheduled delivery</h2><p>The scheduler checks due announcements every minute, prevents duplicate deliveries and expires outdated messages.</p></div><a href="{{ route('web.admin.communication.index', ['section' => 'history']) }}">Review history</a></article>
            <article><span>04</span><div><h2>Attendance alerts</h2><p>Parents can receive a portal alert whenever a linked child is marked absent. Optional email delivery uses the same queued mail system.</p></div><a href="{{ route('web.admin.communication.index', ['section' => 'settings']) }}">Configure alerts</a></article>
        </section>
    @elseif ($activeSection === 'compose')
        <section class="content-section">
            <div class="section-heading"><div><p class="eyebrow">New communication</p><h2>Compose targeted announcement</h2></div><p>A message is never sent twice to the same user for the same announcement.</p></div>
            <form method="POST" action="{{ route('web.admin.communication.announcements.store') }}" class="long-form communication-compose-form">
                @csrf
                <fieldset class="form-section"><legend>Message</legend><div class="form-grid form-grid-2"><label class="field-group form-span-full"><span>Title</span><input name="title" value="{{ old('title') }}" required></label><label class="field-group"><span>Category</span><input name="category" value="{{ old('category', 'Announcement') }}"></label><label class="field-group"><span>Priority</span><select name="priority" required>@foreach(['low','normal','high','urgent'] as $priority)<option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ str($priority)->headline() }}</option>@endforeach</select></label><label class="field-group form-span-full"><span>Short summary</span><textarea name="excerpt" rows="3">{{ old('excerpt') }}</textarea></label><label class="field-group form-span-full"><span>Full message</span><textarea name="body" rows="9" required>{{ old('body') }}</textarea></label></div></fieldset>

                <fieldset class="form-section"><legend>Audience</legend><label class="field-group"><span>Audience mode</span><select name="audience_mode" required><option value="all" @selected(old('audience_mode') === 'all')>Every active user</option><option value="targeted" @selected(old('audience_mode', 'targeted') === 'targeted')>Selected roles, classes or people</option></select></label><div class="target-audience-grid"><section><h3>Roles</h3>@foreach($roles as $value => $label)<label class="check-row"><input name="role_targets[]" type="checkbox" value="{{ $value }}" @checked(in_array($value, old('role_targets', []), true))><span>{{ $label }}</span></label>@endforeach</section><section><h3>Classes</h3><div class="target-scroll-list">@foreach($classes as $class)<label class="check-row"><input name="class_ids[]" type="checkbox" value="{{ $class->id }}" @checked(in_array($class->id, old('class_ids', [])))><span>{{ $class->display_name }}</span></label>@endforeach</div></section><section><h3>Individual users</h3><div class="target-scroll-list">@foreach($users as $user)<label class="check-row"><input name="user_ids[]" type="checkbox" value="{{ $user->id }}" @checked(in_array($user->id, old('user_ids', [])))><span>{{ $user->fullName() }} · {{ $user->roleLabel() }}</span></label>@endforeach</div></section></div></fieldset>

                <fieldset class="form-section"><legend>Channels and timing</legend><div class="check-grid"><label class="check-row"><input name="portal_enabled" type="checkbox" value="1" @checked(old('portal_enabled', true))><span>Portal notification inbox</span></label><label class="check-row"><input name="email_enabled" type="checkbox" value="1" @checked(old('email_enabled', filter_var($settings['communication_default_email_enabled'] ?? false, FILTER_VALIDATE_BOOL)))><span>Queued email where an address is available</span></label><label class="check-row"><input name="is_public" type="checkbox" value="1" @checked(old('is_public'))><span>Also publish in public News</span></label></div><div class="form-grid form-grid-2"><label class="field-group"><span>Start date/time</span><input name="starts_at" type="datetime-local" value="{{ old('starts_at') }}"><small>Leave blank for immediate eligibility.</small></label><label class="field-group"><span>Expiry date/time</span><input name="expires_at" type="datetime-local" value="{{ old('expires_at') }}"></label></div><label class="check-row"><input name="dispatch_now" type="checkbox" value="1" @checked(old('dispatch_now'))><span>Dispatch now when the start time is due; otherwise save as a scheduled message</span></label></fieldset>
                <div class="form-actions"><button class="primary-button" type="submit">Save announcement</button></div>
            </form>
        </section>
    @elseif ($activeSection === 'history')
        <section class="communication-history-list">
            @forelse ($announcements as $announcement)
                <details class="communication-history-row">
                    <summary><span><strong>{{ $announcement->title }}</strong><small>{{ $announcement->category ?: 'Announcement' }} · {{ str($announcement->priority)->headline() }} priority · {{ str($announcement->status)->headline() }}</small></span><span><strong>{{ number_format($announcement->deliveries->count()) }}</strong><small>recipients</small></span></summary>
                    <div class="communication-history-detail"><dl><div><dt>Audience</dt><dd>{{ $announcement->audience_mode === 'all' ? 'Every active user' : 'Targeted selection' }}</dd></div><div><dt>Channels</dt><dd>{{ collect([$announcement->portal_enabled ? 'Portal' : null, $announcement->email_enabled ? 'Email' : null])->filter()->implode(' + ') }}</dd></div><div><dt>Starts</dt><dd>{{ $announcement->starts_at?->format('d M Y H:i') ?: 'Immediate' }}</dd></div><div><dt>Expires</dt><dd>{{ $announcement->expires_at?->format('d M Y H:i') ?: 'No expiry' }}</dd></div><div><dt>Dispatched</dt><dd>{{ $announcement->dispatched_at?->format('d M Y H:i') ?: 'Not yet' }}</dd></div><div><dt>Author</dt><dd>{{ $announcement->author?->fullName() ?? 'System' }}</dd></div></dl><p>{{ $announcement->excerpt ?: str($announcement->body)->limit(350) }}</p><div class="delivery-status-strip">@foreach(['queued','delivered','partial','failed'] as $status)<span><strong>{{ $announcement->deliveries->where('status', $status)->count() }}</strong>{{ str($status)->headline() }}</span>@endforeach</div><div class="inline-actions">@if(in_array($announcement->status, ['draft','scheduled'], true))<form method="POST" action="{{ route('web.admin.communication.announcements.dispatch', $announcement) }}">@csrf<button class="primary-button" type="submit">Dispatch due message</button></form>@endif @if($announcement->status === 'scheduled' && !$announcement->dispatched_at)<form method="POST" action="{{ route('web.admin.communication.announcements.cancel', $announcement) }}">@csrf @method('PATCH')<button class="secondary-button" type="submit">Cancel schedule</button></form>@endif</div></div>
                </details>
            @empty
                <div class="empty-state">No internal announcements have been created.</div>
            @endforelse
        </section>
        <div class="pagination-wrap">{{ $announcements->links() }}</div>
    @elseif ($activeSection === 'settings')
        <section class="content-section"><div class="section-heading"><div><p class="eyebrow">Automatic communication</p><h2>Attendance and default delivery settings</h2></div></div><form method="POST" action="{{ route('web.admin.communication.settings.update') }}" class="long-form">@csrf @method('PUT')<fieldset class="form-section"><legend>Parent absence alerts</legend><div class="check-grid"><label class="check-row"><input name="absence_notifications_enabled" type="checkbox" value="1" @checked(filter_var($settings['absence_notifications_enabled'] ?? true, FILTER_VALIDATE_BOOL))><span>Create a Parent portal alert when a linked Student is marked absent</span></label><label class="check-row"><input name="absence_email_enabled" type="checkbox" value="1" @checked(filter_var($settings['absence_email_enabled'] ?? false, FILTER_VALIDATE_BOOL))><span>Also queue an email when the Parent has an email address</span></label></div></fieldset><fieldset class="form-section"><legend>Compose defaults</legend><div class="form-grid form-grid-2"><label class="check-row"><input name="communication_default_email_enabled" type="checkbox" value="1" @checked(filter_var($settings['communication_default_email_enabled'] ?? false, FILTER_VALIDATE_BOOL))><span>Enable email by default on new announcements</span></label><label class="field-group"><span>Default expiry days</span><input name="communication_default_expiry_days" type="number" min="1" max="365" value="{{ $settings['communication_default_expiry_days'] ?? 30 }}"></label></div></fieldset><div class="form-actions"><button class="primary-button" type="submit">Save communication settings</button></div></form></section>
    @endif
@endsection
