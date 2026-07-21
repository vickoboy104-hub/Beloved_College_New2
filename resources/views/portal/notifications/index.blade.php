@extends('layouts.portal')

@php $routePrefix = request()->routeIs('app.*') ? 'app' : 'web'; @endphp

@section('title', 'Notifications')

@section('content')
    <header class="page-heading page-heading-actions">
        <div>
            <p class="eyebrow">Notification centre</p>
            <h1>Notifications</h1>
            <p>School announcements, attendance alerts and important portal updates appear here.</p>
        </div>
        @if ($unreadCount > 0)
            <form method="POST" action="{{ route($routePrefix.'.notifications.read-all') }}">@csrf @method('PATCH')<button class="secondary-button" type="submit">Mark all read</button></form>
        @endif
    </header>

    <section class="metric-row" aria-label="Notification summary">
        <article class="metric-item"><span>Unread</span><strong>{{ number_format($unreadCount) }}</strong></article>
        <article class="metric-item"><span>Showing</span><strong>{{ number_format($notifications->count()) }}</strong></article>
        <article class="metric-item"><span>Filter</span><strong>{{ str($filter)->headline() }}</strong></article>
    </section>

    <nav class="workspace-tabs compact-tabs" aria-label="Notification filters">
        <a href="{{ route($routePrefix.'.notifications.index', ['filter' => 'all']) }}" @class(['is-active' => $filter === 'all'])>All</a>
        <a href="{{ route($routePrefix.'.notifications.index', ['filter' => 'unread']) }}" @class(['is-active' => $filter === 'unread'])>Unread</a>
    </nav>

    <section class="notification-inbox-list">
        @forelse ($notifications as $notification)
            @php
                $data = $notification->data;
                $priority = $data['priority'] ?? 'normal';
            @endphp
            <article @class(['notification-row', 'is-unread' => is_null($notification->read_at), 'priority-'.$priority])>
                <div class="notification-row-marker" aria-hidden="true"></div>
                <div class="notification-row-content">
                    <div class="notification-row-meta"><span>{{ $data['category'] ?? 'Notification' }}</span><time>{{ $notification->created_at->diffForHumans() }}</time></div>
                    <h2>{{ $data['title'] ?? 'School notification' }}</h2>
                    <p>{{ $data['body'] ?? '' }}</p>
                </div>
                <div class="notification-row-actions">
                    @if (is_null($notification->read_at))
                        <form method="POST" action="{{ route($routePrefix.'.notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="primary-button" type="submit">Open</button></form>
                    @elseif ($data['url'] ?? null)
                        <a class="secondary-link" href="{{ $data['url'] }}">Open</a>
                    @endif
                    <form method="POST" action="{{ route($routePrefix.'.notifications.destroy', $notification->id) }}">@csrf @method('DELETE')<button class="text-button" type="submit">Remove</button></form>
                </div>
            </article>
        @empty
            <div class="empty-state">{{ $filter === 'unread' ? 'You have no unread notifications.' : 'No notifications are available yet.' }}</div>
        @endforelse
    </section>

    <div class="pagination-wrap">{{ $notifications->links() }}</div>
@endsection
