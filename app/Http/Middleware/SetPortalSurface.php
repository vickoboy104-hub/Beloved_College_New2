<?php

namespace App\Http\Middleware;

use App\Enums\PortalSurface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetPortalSurface
{
    public const REQUEST_ATTRIBUTE = 'portal_surface';

    public function handle(Request $request, Closure $next, string $surface): Response
    {
        $portalSurface = PortalSurface::tryFrom($surface);

        abort_unless($portalSurface, 500, 'The configured portal surface is invalid.');

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $portalSurface);
        app()->instance(PortalSurface::class, $portalSurface);
        View::share('portalSurface', $portalSurface);

        return $next($request);
    }
}
