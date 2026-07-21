<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $announcementId,
        public readonly int $deliveryId,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $announcement = $this->announcement();
        $channels = [];

        if ($announcement->portal_enabled) {
            $channels[] = 'database';
        }

        if ($announcement->email_enabled && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'mail' => (string) config('queue.default', 'database'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'mail' => 'notifications',
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'school-announcement';
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $announcement = $this->announcement();

        return [
            'announcement_id' => $announcement->id,
            'delivery_id' => $this->deliveryId,
            'title' => $announcement->title,
            'body' => $announcement->excerpt ?: str($announcement->body)->stripTags()->limit(260)->toString(),
            'category' => $announcement->category ?: 'Announcement',
            'priority' => $announcement->priority,
            'url' => $this->portalUrl($notifiable),
            'starts_at' => $announcement->starts_at?->toIso8601String(),
            'expires_at' => $announcement->expires_at?->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $announcement = $this->announcement();

        return (new MailMessage)
            ->subject($announcement->title)
            ->greeting('Hello '.$this->recipientName($notifiable).',')
            ->line($announcement->excerpt ?: str($announcement->body)->stripTags()->limit(500)->toString())
            ->action('Open notification', $this->portalUrl($notifiable))
            ->line('This message was sent by Beloved College.');
    }

    public function delivery(): ?AnnouncementDelivery
    {
        return AnnouncementDelivery::query()->find($this->deliveryId);
    }

    private function announcement(): Announcement
    {
        return Announcement::query()->findOrFail($this->announcementId);
    }

    private function portalUrl(object $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->hasAnyRole('student', 'parent')) {
            return route('app.notifications.index');
        }

        return route('web.notifications.index');
    }

    private function recipientName(object $notifiable): string
    {
        return $notifiable instanceof User ? $notifiable->fullName() : 'there';
    }
}
