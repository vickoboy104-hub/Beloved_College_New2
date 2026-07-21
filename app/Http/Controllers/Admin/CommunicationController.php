<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\User;
use App\Services\Communication\CommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        $sections = ['overview', 'compose', 'history', 'settings'];
        $activeSection = in_array($request->query('section'), $sections, true)
            ? $request->query('section')
            : 'overview';
        $announcements = Announcement::query()
            ->with(['author', 'deliveries'])
            ->latest()
            ->paginate(30);

        return view('admin.communication.index', [
            'activeSection' => $activeSection,
            'announcements' => $announcements,
            'classes' => SchoolClass::query()->orderBy('name')->orderBy('section')->get(),
            'users' => User::query()
                ->where('status', 'active')
                ->whereNull('archived_at')
                ->orderBy('first_name')
                ->orderBy('name')
                ->get(),
            'roles' => UserRole::options(),
            'settings' => Setting::forAdminForm(),
            'counts' => [
                'drafts' => Announcement::query()->where('status', 'draft')->count(),
                'scheduled' => Announcement::query()->where('status', 'scheduled')->count(),
                'dispatched' => Announcement::query()->where('status', 'dispatched')->count(),
                'deliveries' => \App\Models\AnnouncementDelivery::query()->count(),
                'unread' => \Illuminate\Notifications\DatabaseNotification::query()->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function store(Request $request, CommunicationService $communication): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body' => ['required', 'string', 'max:30000'],
            'category' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'audience_mode' => ['required', Rule::in(['all', 'targeted'])],
            'role_targets' => ['nullable', 'array'],
            'role_targets.*' => [Rule::enum(UserRole::class)],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:school_classes,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'portal_enabled' => ['nullable', 'boolean'],
            'email_enabled' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'dispatch_now' => ['nullable', 'boolean'],
        ]);

        if (! $request->boolean('portal_enabled') && ! $request->boolean('email_enabled')) {
            return back()->withErrors([
                'channels' => 'Enable portal delivery, email delivery, or both.',
            ])->withInput();
        }

        if ($data['audience_mode'] === 'targeted'
            && empty($data['role_targets'] ?? [])
            && empty($data['class_ids'] ?? [])
            && empty($data['user_ids'] ?? [])) {
            return back()->withErrors([
                'audience_mode' => 'Select at least one role, class, or individual user.',
            ])->withInput();
        }

        $announcement = $communication->createAnnouncement($request->user(), [
            ...$data,
            'portal_enabled' => $request->boolean('portal_enabled'),
            'email_enabled' => $request->boolean('email_enabled'),
            'is_public' => $request->boolean('is_public'),
            'dispatch_now' => $request->boolean('dispatch_now'),
        ]);

        return redirect()
            ->route('web.admin.communication.index', ['section' => 'history'])
            ->with('status', $announcement->status === 'dispatched'
                ? 'Announcement dispatched successfully.'
                : 'Announcement saved successfully.');
    }

    public function dispatch(Announcement $announcement, CommunicationService $communication): RedirectResponse
    {
        $queued = $communication->dispatch($announcement);

        return back()->with('status', number_format($queued).' notification deliveries were queued.');
    }

    public function cancel(Announcement $announcement, CommunicationService $communication): RedirectResponse
    {
        $communication->cancel($announcement);

        return back()->with('status', 'Scheduled announcement cancelled.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'absence_notifications_enabled' => ['nullable', 'boolean'],
            'absence_email_enabled' => ['nullable', 'boolean'],
            'communication_default_email_enabled' => ['nullable', 'boolean'],
            'communication_default_expiry_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        Setting::setMany([
            'absence_notifications_enabled' => $request->boolean('absence_notifications_enabled') ? '1' : '0',
            'absence_email_enabled' => $request->boolean('absence_email_enabled') ? '1' : '0',
            'communication_default_email_enabled' => $request->boolean('communication_default_email_enabled') ? '1' : '0',
            'communication_default_expiry_days' => $data['communication_default_expiry_days'] ?? 30,
        ], 'communication');

        return back()->with('status', 'Communication settings updated.');
    }
}
