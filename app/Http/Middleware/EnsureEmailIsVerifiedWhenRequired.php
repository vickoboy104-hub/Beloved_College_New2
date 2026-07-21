<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedWhenRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() === config('platform.hosts.public')
            || ! $request->user()
            || $request->user()->must_change_password
            || ! Schema::hasTable('settings')
            || ! filter_var(Setting::getValue('email_verification_required', false), FILTER_VALIDATE_BOOL)
            || blank($request->user()->email)
            || $request->user()->hasVerifiedEmail()
            || $this->isAllowedRoute($request)) {
            return $next($request);
        }

        return $this->verificationRedirect($request);
    }

    private function isAllowedRoute(Request $request): bool
    {
        return $request->routeIs(
            'web.verification.*',
            'app.verification.*',
            'web.logout',
            'app.logout',
            'web.password-change.*',
            'app.password-change.*',
            'web.security.*',
            'app.security.*',
            'web.notifications.*',
            'app.notifications.*',
        );
    }

    private function verificationRedirect(Request $request): RedirectResponse
    {
        $prefix = $request->getHost() === config('platform.hosts.app') ? 'app' : 'web';

        return redirect()
            ->route($prefix.'.verification.notice')
            ->with('status', 'Verify your email address before continuing.');
    }
}
