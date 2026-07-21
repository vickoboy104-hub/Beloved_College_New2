<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ThemeMode;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ThemeRevision;
use App\Services\Website\ThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ThemeController extends Controller
{
    public function index(ThemeService $themes): View
    {
        return view('admin.website.themes', [
            'modes' => ThemeMode::cases(),
            'defaultMode' => $themes->defaultMode(),
            'allowUserSelection' => $themes->userSelectionAllowed(),
            'classicTokens' => $themes->tokens(ThemeMode::Classic),
            'darkTokens' => $themes->tokens(ThemeMode::Dark),
            'revisions' => ThemeRevision::query()->with('creator')->latest()->take(40)->get(),
        ]);
    }

    public function saveDraft(Request $request, string $mode, ThemeService $themes): RedirectResponse
    {
        $themeMode = ThemeMode::tryFrom($mode);
        abort_unless($themeMode, 404);
        $data = $this->validatedTheme($request, $themeMode, $themes);
        $themes->saveDraft($request->user(), $themeMode, $data['tokens'], $data['notes'] ?? null);

        return back()->with('status', $themeMode->label().' theme draft saved.');
    }

    public function publish(Request $request, string $mode, ThemeService $themes): RedirectResponse
    {
        $themeMode = ThemeMode::tryFrom($mode);
        abort_unless($themeMode, 404);
        $data = $this->validatedTheme($request, $themeMode, $themes);
        $themes->publish($request->user(), $themeMode, $data['tokens'], $data['notes'] ?? null);

        return back()->with('status', $themeMode->label().' theme published successfully.');
    }

    public function rollback(Request $request, ThemeRevision $revision, ThemeService $themes): RedirectResponse
    {
        $themes->rollback($request->user(), $revision);

        return back()->with('status', $revision->mode->label().' theme rolled back using revision #'.$revision->id.'.');
    }

    public function preferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme_default_mode' => ['required', Rule::enum(ThemeMode::class)],
            'theme_allow_user_selection' => ['nullable', 'boolean'],
        ]);
        Setting::setMany([
            'theme_default_mode' => $data['theme_default_mode'],
            'theme_allow_user_selection' => $request->boolean('theme_allow_user_selection') ? '1' : '0',
        ], 'theme');

        return back()->with('status', 'Global theme preferences updated.');
    }

    public function preview(string $mode, ThemeService $themes): View
    {
        $themeMode = ThemeMode::tryFrom($mode);
        abort_unless($themeMode, 404);

        return view('admin.website.theme-preview', [
            'themeMode' => $themeMode,
            'tokens' => $themes->tokens($themeMode),
        ]);
    }

    /**
     * @return array{tokens: array<string, string>, notes?: string|null}
     */
    private function validatedTheme(Request $request, ThemeMode $mode, ThemeService $themes): array
    {
        $tokenRules = collect(array_keys($themes->defaults($mode)))
            ->mapWithKeys(fn (string $key) => ['tokens.'.$key => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/']])
            ->all();

        return $request->validate([
            'tokens' => ['required', 'array'],
            ...$tokenRules,
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
