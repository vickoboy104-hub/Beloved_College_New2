<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PortalSurface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Identity\SecurityEventService;
use App\Services\Identity\SessionSecurityService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route($this->surfaceRoute('security.index'));
        }

        return view('auth.reset-password', [
            'surface' => app(PortalSurface::class),
            'token' => $token,
            'email' => (string) $request->query('email'),
        ]);
    }

    public function store(
        Request $request,
        SessionSecurityService $sessions,
        SecurityEventService $security,
    ): RedirectResponse {
        if ($request->user()) {
            return redirect()->route($this->surfaceRoute('security.index'));
        }

        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(10)->letters()->mixedCase()->numbers(),
            ],
        ]);
        $eligibleUser = User::query()->where('email', $data['email'])->first();

        if (! $eligibleUser?->isActive()) {
            return back()
                ->withErrors(['email' => __('passwords.token')])
                ->withInput($request->only('email'));
        }

        $resetUser = null;
        $status = Password::reset(
            $data,
            function (User $user, string $password) use (&$resetUser, $sessions): void {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();
                $sessions->revokeAll($user);
                $resetUser = $user;

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PasswordReset) {
            return back()
                ->withErrors(['email' => __($status)])
                ->withInput($request->only('email'));
        }

        if ($resetUser instanceof User) {
            $security->alert(
                $resetUser,
                'password.reset',
                'Your account password was reset',
                'Your Beloved College password was reset and all existing sessions were signed out.',
                $request,
                ['all_sessions_revoked' => true],
                'critical',
            );
        }

        return redirect()
            ->route($this->surfaceRoute('login'))
            ->with('status', 'Password reset successfully. Sign in with your new password.');
    }

    private function surfaceRoute(string $route): string
    {
        return (app(PortalSurface::class) === PortalSurface::AppPortal ? 'app' : 'web').".{$route}";
    }
}
