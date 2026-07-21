<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PortalSurface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect()
            ->route($this->surfaceRoute('dashboard'))
            ->with('status', 'Password changed successfully.');
    }

    private function surfaceRoute(string $route): string
    {
        return (app(PortalSurface::class) === PortalSurface::AppPortal ? 'app' : 'web').".{$route}";
    }
}
