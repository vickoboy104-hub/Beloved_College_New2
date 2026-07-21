<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PortalSurface;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Identity\SecurityEventService;
use App\Services\Identity\SessionSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function index(
        Request $request,
        SessionSecurityService $sessions,
    ): View {
        return view('auth.security', [
            'surface' => app(PortalSurface::class),
            'sessions' => $sessions->sessions($request->user(), $request->session()->getId()),
            'securityEvents' => $request->user()->securityEvents()->latest('occurred_at')->limit(30)->get(),
            'emailVerificationRequired' => filter_var(
                Setting::getValue('email_verification_required', false),
                FILTER_VALIDATE_BOOL,
            ),
        ]);
    }

    public function updatePassword(
        Request $request,
        SessionSecurityService $sessions,
        SecurityEventService $security,
    ): RedirectResponse {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->letters()->mixedCase()->numbers(),
            ],
        ]);
        $user = $request->user();
        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();
        $revoked = $sessions->revokeOthers($user, $request->session()->getId());
        $request->session()->regenerate();
        $security->alert(
            $user,
            'password.changed',
            'Your account password was changed',
            'Your password was changed and '.number_format($revoked).' other active session(s) were signed out.',
            $request,
            ['revoked_sessions' => $revoked],
            'critical',
        );

        return back()->with('status', 'Password updated and other sessions signed out.');
    }

    public function revokeSession(
        Request $request,
        string $session,
        SessionSecurityService $sessions,
        SecurityEventService $security,
    ): RedirectResponse {
        $deleted = $sessions->revoke($request->user(), $session, $request->session()->getId());

        if (! $deleted) {
            abort(404);
        }

        $security->record(
            $request->user(),
            'session.revoked',
            'warning',
            $request,
            ['session_id_hash' => hash('sha256', $session)],
        );

        return back()->with('status', 'The selected session was signed out.');
    }

    public function revokeOtherSessions(
        Request $request,
        SessionSecurityService $sessions,
        SecurityEventService $security,
    ): RedirectResponse {
        $request->validate([
            'current_password' => ['required', 'current_password:web'],
        ]);
        $revoked = $sessions->revokeOthers($request->user(), $request->session()->getId());
        $security->alert(
            $request->user(),
            'sessions.revoked',
            'Other account sessions were signed out',
            number_format($revoked).' other active session(s) were revoked from your Beloved College account.',
            $request,
            ['revoked_sessions' => $revoked],
            'warning',
        );

        return back()->with('status', number_format($revoked).' other session(s) signed out.');
    }
}
