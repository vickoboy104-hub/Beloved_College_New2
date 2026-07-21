<?php

use App\Http\Middleware\AuditUserActions;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\SetPortalSurface;
use App\Http\Middleware\TrackLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AuditUserActions::class,
        ]);

        $middleware->alias([
            'surface' => SetPortalSurface::class,
            'active' => EnsureAccountIsActive::class,
            'password.changed' => RequirePasswordChange::class,
            'permission' => EnsurePermission::class,
            'last.seen' => TrackLastSeen::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
