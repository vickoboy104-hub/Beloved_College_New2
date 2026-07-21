<?php

namespace App\Services\Communication;

use App\Enums\AttendanceStatus;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\AttendanceRecord;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SchoolAnnouncementNotification;
use App\Notifications\StudentAbsenceNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunicationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createAnnouncement(User $actor, array $data): Announcement
    {
        $startsAt = filled($data['starts_at'] ?? null) ? $data['starts_at'] : null;
        $status = ($data['dispatch_now'] ?? false)
            ? 'scheduled'
            : (filled($startsAt) ? 'scheduled' : 'draft');
        $isPublic = (bool) ($data['is_public'] ?? false);

        $announcement = Announcement::query()->create([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['title']),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'] ?? null,
            'category' => $data['category'] ?? 'Announcement',
            'priority' => $data['priority'] ?? 'normal',
            'audience_mode' => $data['audience_mode'] ?? 'all',
            'role_targets' => array_values($data['role_targets'] ?? []),
            'class_ids' => array_values(array_map('intval', $data['class_ids'] ?? [])),
            'user_ids' => array_values(array_map('intval', $data['user_ids'] ?? [])),
            'portal_enabled' => (bool) ($data['portal_enabled'] ?? true),
            'email_enabled' => (bool) ($data['email_enabled'] ?? false),
            'is_published' => $isPublic,
            'published_at' => $isPublic ? ($startsAt ?: now()) : null,
            'starts_at' => $startsAt,
            'expires_at' => $data['expires_at'] ?? null,
            'status' => $status,
            'author_id' => $actor->id,
        ]);

        if ($data['dispatch_now'] ?? false) {
            $this->dispatch($announcement);
        }

        return $announcement->fresh();
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(Announcement $announcement): Collection
    {
        $query = User::query()
            ->where('status', 'active')
            ->whereNull('archived_at');

        if ($announcement->audience_mode === 'all') {
            return $query->orderBy('id')->get();
        }

        $userIds = collect($announcement->user_ids ?? [])->map(fn (mixed $id) => (int) $id);
        $roles = collect($announcement->role_targets ?? [])->filter()->values();
        $classIds = collect($announcement->class_ids ?? [])->map(fn (mixed $id) => (int) $id);

        if ($classIds->isNotEmpty()) {
            $studentUsers = Student::query()
                ->whereIn('school_class_id', $classIds)
                ->whereNull('archived_at')
                ->get(['user_id', 'parent_user_id']);
            $userIds = $userIds
                ->merge($studentUsers->pluck('user_id'))
                ->merge($studentUsers->pluck('parent_user_id')->filter());
        }

        if ($userIds->isEmpty() && $roles->isEmpty()) {
            return collect();
        }

        $query->where(function (Builder $builder) use ($userIds, $roles): void {
            if ($userIds->isNotEmpty()) {
                $builder->whereIn('id', $userIds->unique()->values());
            }

            if ($roles->isNotEmpty()) {
                $method = $userIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                $builder->{$method}('role', $roles);
            }
        });

        return $query->orderBy('id')->get();
    }

    public function recipientCount(Announcement $announcement): int
    {
        return $this->recipients($announcement)->count();
    }

    public function dispatch(Announcement $announcement): int
    {
        $announcement->refresh();

        if ($announcement->dispatched_at || $announcement->status === 'dispatched') {
            return 0;
        }

        if ($announcement->isExpired()) {
            $announcement->update(['status' => 'expired']);

            return 0;
        }

        if ($announcement->starts_at?->isFuture()) {
            $announcement->update(['status' => 'scheduled']);

            return 0;
        }

        if (! $announcement->portal_enabled && ! $announcement->email_enabled) {
            throw ValidationException::withMessages([
                'channels' => 'Enable portal delivery, email delivery, or both.',
            ]);
        }

        $recipients = $this->recipients($announcement);
        $queued = 0;

        foreach ($recipients as $user) {
            $channels = collect([
                $announcement->portal_enabled ? 'database' : null,
                $announcement->email_enabled && filled($user->email) ? 'mail' : null,
            ])->filter()->values()->all();

            if ($channels === []) {
                continue;
            }

            $delivery = AnnouncementDelivery::query()->firstOrCreate(
                [
                    'announcement_id' => $announcement->id,
                    'user_id' => $user->id,
                ],
                [
                    'channels' => $channels,
                    'status' => 'queued',
                    'queued_at' => now(),
                ],
            );

            if (! $delivery->wasRecentlyCreated) {
                continue;
            }

            $user->notify(new SchoolAnnouncementNotification($announcement->id, $delivery->id));
            $queued++;
        }

        $announcement->update([
            'status' => 'dispatched',
            'dispatched_at' => now(),
            'published_at' => $announcement->is_published
                ? ($announcement->published_at ?: now())
                : $announcement->published_at,
        ]);

        return $queued;
    }

    public function dispatchDue(): int
    {
        $count = 0;

        Announcement::query()
            ->dueForDispatch()
            ->orderBy('starts_at')
            ->each(function (Announcement $announcement) use (&$count): void {
                $count += $this->dispatch($announcement);
            });

        Announcement::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNotIn('status', ['expired', 'cancelled'])
            ->update(['status' => 'expired']);

        return $count;
    }

    public function cancel(Announcement $announcement): void
    {
        if ($announcement->dispatched_at) {
            throw ValidationException::withMessages([
                'announcement' => 'A dispatched announcement cannot be cancelled retroactively.',
            ]);
        }

        $announcement->update(['status' => 'cancelled']);
    }

    public function notifyAbsence(AttendanceRecord $record): bool
    {
        $record->loadMissing(['student.user', 'student.parent']);

        if ($record->status !== AttendanceStatus::Absent
            || $record->absence_notified_at
            || ! filter_var(Setting::getValue('absence_notifications_enabled', true), FILTER_VALIDATE_BOOL)
            || ! $record->student->parent) {
            return false;
        }

        DB::transaction(function () use ($record): void {
            $locked = AttendanceRecord::query()->lockForUpdate()->findOrFail($record->id);

            if ($locked->absence_notified_at) {
                return;
            }

            $record->student->parent->notify(new StudentAbsenceNotification($record->id));
            $locked->update(['absence_notified_at' => now()]);
        });

        return true;
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'announcement';
        $slug = $base;
        $counter = 2;

        while (Announcement::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
