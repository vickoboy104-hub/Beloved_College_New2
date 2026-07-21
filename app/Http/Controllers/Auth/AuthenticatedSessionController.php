<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LoginAudience;
use App\Enums\PortalSurface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Identity\LoginIdentifierResolver;
use App\Services\Identity\SecurityEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request, ?string $audience = null): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route($this->surfaceRoute('dashboard'));
        }

        $loginAudience = LoginAudience::tryFrom(
            $audience ?: (string) $request->query('audience', LoginAudience::Generic->value),
        ) ?? LoginAudience::Generic;

        return view('auth.login', [
            'audience' => $loginAudience,
            'surface' => app(PortalSurface::class),
        ]);
    }

    public function store(
        LoginRequest $request,
        LoginIdentifierResolver $resolver,
        SecurityEventService $security,
    ): RedirectResponse {
        $request->authenticate($resolver);
        $request->session()->regenerate();
        $security->loginSucceeded($request->user(), $request);

        return redirect()->intended(route($this->surfaceRoute('dashboard'), absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($this->surfaceRoute('home'));
    }

    private function surfaceRoute(string $route): string
    {
        $surface = app(PortalSurface::class);

        return ($surface === PortalSurface::AppPortal ? 'app' : 'web').".{$route}";
    }
}
