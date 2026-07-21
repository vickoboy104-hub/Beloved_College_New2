<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = in_array($request->query('filter'), ['all', 'unread'], true)
            ? $request->query('filter')
            : 'all';
        $query = $request->user()->notifications()->latest();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        return view('portal.notifications.index', [
            'notifications' => $query->paginate(30)->withQueryString(),
            'filter' => $filter,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $this->ownedNotification($request, $notification);
        $record->markAsRead();
        $url = data_get($record->data, 'url');

        return filled($url)
            ? redirect()->to($url)
            : back()->with('status', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $this->ownedNotification($request, $notification)->delete();

        return back()->with('status', 'Notification removed.');
    }

    private function ownedNotification(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }
}
