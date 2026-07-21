<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use App\Services\System\MailConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ContactMessage $contactMessage)
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

        return (new MailMessage)
            ->subject('New website enquiry: '.($this->contactMessage->subject ?: 'General enquiry'))
            ->greeting('A new public website enquiry has been received.')
            ->line('Name: '.$this->contactMessage->name)
            ->line('Email: '.($this->contactMessage->email ?: 'Not provided'))
            ->line('Phone: '.($this->contactMessage->phone ?: 'Not provided'))
            ->line('Subject: '.($this->contactMessage->subject ?: 'General enquiry'))
            ->line($this->contactMessage->message)
            ->line('Open the Website CMS contact inbox to review and update this enquiry.');
    }
}
