<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemTestEmailNotification extends Notification
{
    public function __construct(
        public readonly string $requestedBy,
        public readonly string $environment,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Beloved College mail configuration test')
            ->greeting('Mail delivery test successful')
            ->line('This test confirms that the configured mail transport accepted a message from the Beloved College application.')
            ->line('Requested by: '.$this->requestedBy)
            ->line('Environment: '.$this->environment)
            ->line('Sent at: '.now()->format('d M Y H:i:s'));
    }
}
