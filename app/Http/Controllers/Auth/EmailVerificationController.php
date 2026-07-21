<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PortalSurface;
use App\Http\Controllers\Controller;
use App\Services\Identity\SecurityEventService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if (blank($request->user()->email)) {
            return redirect()
                ->route($this->surfaceRoute('security.index'))
                ->withErrors(['email' => 'Add an email address through the school office before requesting verification.']);
        }

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($this->surfaceRoute('dashboard'));
        }

        return view('auth.verify-email', [
            'surface' => app(PortalSurface::class),
            'email' => $request->user()->email,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        if (blank($request->user()->email)) {
            return back()->withErrors([
                'email' => 'No email address is assigned to this account.',
            ]);
        }

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()
                ->route($this->surfaceRoute('dashboard'))
                ->with('status', 'Your email address is already verified.');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'A new verification link has been sent.');
    }

    public function verify(
        Request $request,
        string $id,
        string $hash,
        SecurityEventService $security,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless((string) $user->getKey() === $id, 403);
        abort_unless(hash_equals((string) $hash, sha1($user->getEmailForVerification())), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
            $security->alert(
                $user,
                'email.verified',
                'Email address verified',
                'Your Beloved College account email address was verified successfully.',
                $request,
                ['email' => $user->email],
                'info',
            );
        }

        return redirect()
            ->route($this->surfaceRoute('dashboard'))
            ->with('status', 'Email address verified successfully.');
    }

    private function surfaceRoute(string $route): string
    {
        return (app(PortalSurface::class) === PortalSurface::AppPortal ? 'app' : 'web').".{$route}";
    }
}
