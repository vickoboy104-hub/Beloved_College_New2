<?php

namespace App\Services\Website;

use App\Enums\ThemeMode;
use App\Models\Setting;
use App\Models\ThemeRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ThemeService
{
    /**
     * @return array<string, string>
     */
    public function defaults(ThemeMode $mode): array
    {
        return $mode === ThemeMode::Classic
            ? [
                'page' => '#f7f7f2',
                'surface' => '#ffffff',
                'surface_muted' => '#eef3fb',
                'surface_strong' => '#e4ebf7',
                'primary' => '#1346a0',
                'primary_strong' => '#0b347c',
                'primary_soft' => '#dce8ff',
                'accent' => '#f4c430',
                'accent_soft' => '#fff3b8',
                'text' => '#10213d',
                'text_muted' => '#64748b',
                'border' => '#d7deea',
                'success' => '#147a4b',
                'success_soft' => '#e6f7ef',
                'danger' => '#b42318',
                'danger_soft' => '#fff0ee',
                'header' => '#0b347c',
                'header_text' => '#ffffff',
            ]
            : [
                'page' => '#07111f',
                'surface' => '#0d1b2d',
                'surface_muted' => '#13243a',
                'surface_strong' => '#1a2e49',
                'primary' => '#5b8cff',
                'primary_strong' => '#85a7ff',
                'primary_soft' => '#172f5a',
                'accent' => '#f4c430',
                'accent_soft' => '#453b16',
                'text' => '#f5f8ff',
                'text_muted' => '#9fb0c8',
                'border' => '#263a56',
                'success' => '#5fd49b',
                'success_soft' => '#123829',
                'danger' => '#ff8b7f',
                'danger_soft' => '#401f24',
                'header' => '#07111f',
                'header_text' => '#f5f8ff',
            ];
    }

    public function defaultMode(): ThemeMode
    {
        return ThemeMode::tryFrom((string) Setting::getValue('theme_default_mode', config('platform.default_theme')))
            ?? ThemeMode::Classic;
    }

    public function userSelectionAllowed(): bool
    {
        return filter_var(
            Setting::getValue('theme_allow_user_selection', config('platform.allow_user_theme_selection')),
            FILTER_VALIDATE_BOOL,
        );
    }

    public function effectiveFor(?User $user): ThemeMode
    {
        if ($user && $this->userSelectionAllowed() && $user->preferred_theme instanceof ThemeMode) {
            return $user->preferred_theme;
        }

        return $this->defaultMode();
    }

    /**
     * @return array<string, string>
     */
    public function tokens(ThemeMode $mode): array
    {
        $stored = Setting::getValue('theme_tokens_'.$mode->value);
        $decoded = is_array($stored) ? $stored : json_decode((string) $stored, true);

        return array_replace($this->defaults($mode), is_array($decoded) ? $decoded : []);
    }

    /**
     * @param  array<string, mixed>  $tokens
     * @return array<string, string>
     */
    public function normalize(ThemeMode $mode, array $tokens): array
    {
        $normalized = [];

        foreach (array_keys($this->defaults($mode)) as $key) {
            $value = strtolower(trim((string) ($tokens[$key] ?? '')));

            if (! preg_match('/^#[0-9a-f]{6}$/', $value)) {
                throw ValidationException::withMessages([
                    'tokens.'.$key => str($key)->headline().' must be a six-digit hexadecimal colour.',
                ]);
            }

            $normalized[$key] = $value;
        }

        if ($this->contrast($normalized['text'], $normalized['page']) < 4.5) {
            throw ValidationException::withMessages([
                'tokens.text' => 'Main text and page background must meet a minimum 4.5:1 contrast ratio.',
            ]);
        }

        if ($this->contrast($normalized['header_text'], $normalized['header']) < 4.5) {
            throw ValidationException::withMessages([
                'tokens.header_text' => 'Header text and header background must meet a minimum 4.5:1 contrast ratio.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    public function saveDraft(User $actor, ThemeMode $mode, array $tokens, ?string $notes = null): ThemeRevision
    {
        return ThemeRevision::query()->create([
            'mode' => $mode,
            'tokens' => $this->normalize($mode, $tokens),
            'notes' => $notes,
            'is_published' => false,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    public function publish(User $actor, ThemeMode $mode, array $tokens, ?string $notes = null): ThemeRevision
    {
        $normalized = $this->normalize($mode, $tokens);

        return DB::transaction(function () use ($actor, $mode, $normalized, $notes): ThemeRevision {
            ThemeRevision::query()
                ->where('mode', $mode->value)
                ->where('is_published', true)
                ->update(['is_published' => false]);

            $revision = ThemeRevision::query()->create([
                'mode' => $mode,
                'tokens' => $normalized,
                'notes' => $notes,
                'is_published' => true,
                'published_at' => now(),
                'created_by' => $actor->id,
            ]);

            Setting::setMany([
                'theme_tokens_'.$mode->value => $normalized,
            ], 'theme');

            return $revision;
        });
    }

    public function rollback(User $actor, ThemeRevision $revision): ThemeRevision
    {
        return $this->publish(
            $actor,
            $revision->mode,
            $revision->tokens,
            'Rollback from revision #'.$revision->id,
        );
    }

    /**
     * @param  array<string, string>  $tokens
     */
    public function cssVariables(array $tokens): string
    {
        return collect($tokens)
            ->map(fn (string $value, string $key) => '--'.str_replace('_', '-', $key).': '.$value.';')
            ->implode(' ');
    }

    private function contrast(string $foreground, string $background): float
    {
        $lighter = max($this->luminance($foreground), $this->luminance($background));
        $darker = min($this->luminance($foreground), $this->luminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function luminance(string $hex): float
    {
        $channels = [substr($hex, 1, 2), substr($hex, 3, 2), substr($hex, 5, 2)];
        $values = array_map(function (string $channel): float {
            $value = hexdec($channel) / 255;

            return $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return (0.2126 * $values[0]) + (0.7152 * $values[1]) + (0.0722 * $values[2]);
    }
}
