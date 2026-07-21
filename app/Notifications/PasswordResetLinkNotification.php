<?php

namespace App\Notifications;

use App\Services\System\MailConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $surfacePrefix,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        app(MailConfigurationService::class)->apply();
        $url = route($this->surfacePrefix.'.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset your Beloved College password')
            ->greeting('Password reset request')
            ->line('A password reset was requested for your Beloved College account.')
            ->action('Reset password', $url)
            ->line('This link expires in '.$minutes.' minutes.')
            ->line('Ignore this message if you did not request a reset.');
    }
}
