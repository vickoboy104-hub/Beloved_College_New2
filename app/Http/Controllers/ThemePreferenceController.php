<?php

namespace App\Http\Controllers;

use App\Enums\ThemeMode;
use App\Services\Website\ThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemePreferenceController extends Controller
{
    public function update(Request $request, ThemeService $themes): RedirectResponse
    {
        abort_unless($themes->userSelectionAllowed(), 403);
        $data = $request->validate([
            'preferred_theme' => ['required', Rule::enum(ThemeMode::class)],
        ]);
        $request->user()->update([
            'preferred_theme' => $data['preferred_theme'],
        ]);

        return back()->with('status', ThemeMode::from($data['preferred_theme'])->label().' theme selected.');
    }
}
