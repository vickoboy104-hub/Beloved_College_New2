<?php

namespace App\Notifications;

use App\Services\System\MailConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailAddressNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $surfacePrefix)
    {
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
        $minutes = (int) config('auth.verification.expire', 60);
        $url = URL::temporarySignedRoute(
            $this->surfacePrefix.'.verification.verify',
            now()->addMinutes($minutes),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject('Verify your Beloved College email address')
            ->greeting('Verify your email address')
            ->line('Confirm this email address to strengthen account recovery and security alerts.')
            ->action('Verify email address', $url)
            ->line('This verification link expires in '.$minutes.' minutes.')
            ->line('Ignore this message if you did not request verification.');
    }
}
