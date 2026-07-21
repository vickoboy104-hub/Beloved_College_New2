<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SessionSecurityService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function sessions(User $user, ?string $currentSessionId): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'device' => $this->deviceLabel((string) $session->user_agent),
                'browser' => $this->browserLabel((string) $session->user_agent),
                'last_activity' => now()->setTimestamp((int) $session->last_activity),
                'is_current' => hash_equals((string) $currentSessionId, (string) $session->id),
            ]);
    }

    public function revoke(User $user, string $sessionId, ?string $currentSessionId): bool
    {
        if (hash_equals((string) $currentSessionId, $sessionId)) {
            throw ValidationException::withMessages([
                'session' => 'Use Sign out to end the current session.',
            ]);
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    public function revokeOthers(User $user, ?string $currentSessionId): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($currentSessionId, fn ($query) => $query->where('id', '!=', $currentSessionId))
            ->delete();
    }

    public function revokeAll(User $user): int
    {
        return DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function deviceLabel(string $userAgent): string
    {
        $agent = strtolower($userAgent);

        return match (true) {
            str_contains($agent, 'ipad'), str_contains($agent, 'tablet') => 'Tablet',
            str_contains($agent, 'mobile'), str_contains($agent, 'android'), str_contains($agent, 'iphone') => 'Mobile device',
            $agent === '' => 'Unknown device',
            default => 'Desktop or laptop',
        };
    }

    private function browserLabel(string $userAgent): string
    {
        $agent = strtolower($userAgent);

        return match (true) {
            str_contains($agent, 'edg/') => 'Microsoft Edge',
            str_contains($agent, 'opr/'), str_contains($agent, 'opera') => 'Opera',
            str_contains($agent, 'chrome/') => 'Google Chrome',
            str_contains($agent, 'firefox/') => 'Mozilla Firefox',
            str_contains($agent, 'safari/') => 'Safari',
            $agent === '' => 'Unknown browser',
            default => 'Other browser',
        };
    }
}
