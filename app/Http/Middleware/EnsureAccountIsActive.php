<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isActive()) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route($this->loginRoute($request))
            ->withErrors(['login' => 'This account is inactive. Contact the school administrator.']);
    }

    private function loginRoute(Request $request): string
    {
        return str_starts_with((string) $request->route()?->getName(), 'app.')
            ? 'app.login'
            : 'web.login';
    }
}
