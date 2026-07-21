<?php

namespace App\Services\Identity;

use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AccountSecurityNotification;
use Illuminate\Http\Request;

class SecurityEventService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ?User $user,
        string $event,
        string $severity = 'info',
        ?Request $request = null,
        array $metadata = [],
    ): SecurityEvent {
        return SecurityEvent::query()->create([
            'user_id' => $user?->id,
            'event' => $event,
            'severity' => $severity,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function alert(
        User $user,
        string $event,
        string $title,
        string $message,
        ?Request $request = null,
        array $metadata = [],
        string $severity = 'warning',
    ): SecurityEvent {
        $eventRecord = $this->record($user, $event, $severity, $request, $metadata);
        $user->notify(new AccountSecurityNotification(
            $title,
            $message,
            $severity === 'critical' ? 'urgent' : 'high',
            [
                ...$metadata,
                'ip_address' => $request?->ip(),
                'occurred_at' => now()->format('d M Y H:i:s'),
                'security_event_id' => $eventRecord->id,
            ],
        ));

        return $eventRecord;
    }

    public function loginSucceeded(User $user, Request $request): void
    {
        $previousIp = $user->last_login_ip;
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $event = $this->record($user, 'login.succeeded', 'info', $request, [
            'previous_ip' => $previousIp,
            'surface' => $request->getHost(),
        ]);

        if (filter_var(Setting::getValue('security_login_alerts_enabled', false), FILTER_VALIDATE_BOOL)) {
            $user->notify(new AccountSecurityNotification(
                'New account sign-in',
                'Your Beloved College account was signed in successfully.',
                'normal',
                [
                    'ip_address' => $request->ip(),
                    'occurred_at' => $event->occurred_at->format('d M Y H:i:s'),
                    'security_event_id' => $event->id,
                ],
            ));
        }
    }
}
