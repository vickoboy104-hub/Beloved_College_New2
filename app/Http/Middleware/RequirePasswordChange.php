<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = (string) $request->route()?->getName();

        if (! $user?->must_change_password
            || str_contains($routeName, 'password-change.')
            || str_ends_with($routeName, '.logout')) {
            return $next($request);
        }

        $surface = str_starts_with($routeName, 'app.') ? 'app' : 'web';

        return redirect()->route("{$surface}.password-change.edit");
    }
}
