<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PortalSurface;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route($this->surfaceRoute('dashboard'));
        }

        return view('auth.forgot-password', [
            'surface' => app(PortalSurface::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);
        $user = User::query()->where('email', $data['email'])->first();

        if ($user?->isActive()) {
            Password::sendResetLink(['email' => $data['email']]);
        }

        return back()->with(
            'status',
            'If an active account uses that email address, a password reset link has been sent.',
        );
    }

    private function surfaceRoute(string $route): string
    {
        return (app(PortalSurface::class) === PortalSurface::AppPortal ? 'app' : 'web').".{$route}";
    }
}
