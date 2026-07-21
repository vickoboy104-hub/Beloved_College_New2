<?php

namespace App\Providers;

use App\Notifications\SchoolAnnouncementNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if (! $event->notification instanceof SchoolAnnouncementNotification) {
                return;
            }

            $event->notification->delivery()?->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
            ]);
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            if (! $event->notification instanceof SchoolAnnouncementNotification) {
                return;
            }

            $delivery = $event->notification->delivery();

            if (! $delivery) {
                return;
            }

            $delivery->update([
                'status' => $delivery->delivered_at ? 'partial' : 'failed',
                'failed_at' => now(),
                'failure_reason' => str((string) data_get($event->data, 'exception', 'Notification delivery failed.'))
                    ->limit(2000)
                    ->toString(),
            ]);
        });
    }
}
