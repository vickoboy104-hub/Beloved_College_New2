<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Services\System\MailConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSecurityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly string $priority = 'high',
        public readonly array $metadata = [],
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filter_var(Setting::getValue('security_email_alerts_enabled', true), FILTER_VALIDATE_BOOL)
            && filled($notifiable->email)) {
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
        return ['mail' => 'notifications'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'account-security';
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->message,
            'category' => 'Account security',
            'priority' => $this->priority,
            'url' => $this->portalUrl($notifiable),
            'metadata' => $this->metadata,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        app(MailConfigurationService::class)->apply();

        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Account security update')
            ->line($this->message)
            ->lineIf(filled($this->metadata['ip_address'] ?? null), 'IP address: '.$this->metadata['ip_address'])
            ->lineIf(filled($this->metadata['occurred_at'] ?? null), 'Time: '.$this->metadata['occurred_at'])
            ->action('Review account security', $this->portalUrl($notifiable))
            ->line('Contact the school immediately if you did not perform this action.');
    }

    private function portalUrl(object $notifiable): string
    {
        $prefix = method_exists($notifiable, 'hasAnyRole') && $notifiable->hasAnyRole('student', 'parent')
            ? 'app'
            : 'web';

        return route($prefix.'.security.index');
    }
}
