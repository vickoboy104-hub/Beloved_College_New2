<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PortalSurface;
use App\Http\Controllers\Controller;
use App\Services\Identity\SecurityEventService;
use App\Services\Identity\SessionSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('auth.password-change', [
            'surface' => app(PortalSurface::class),
        ]);
    }

    public function update(
        Request $request,
        SessionSecurityService $sessions,
        SecurityEventService $security,
    ): RedirectResponse {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $user = $request->user();
        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();
        $revoked = $sessions->revokeOthers($user, $request->session()->getId());
        $request->session()->regenerate();
        $security->alert(
            $user,
            'password.changed.initial',
            'Your temporary password was replaced',
            'Your temporary school password was replaced successfully and other active sessions were signed out.',
            $request,
            ['revoked_sessions' => $revoked],
            'warning',
        );

        return redirect()
            ->route($this->surfaceRoute('dashboard'))
            ->with('status', 'Password changed successfully.');
    }

    private function surfaceRoute(string $route): string
    {
        return (app(PortalSurface::class) === PortalSurface::AppPortal ? 'app' : 'web').".{$route}";
    }
}
