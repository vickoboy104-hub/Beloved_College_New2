<?php

namespace App\Enums;

enum ThemeMode: string
{
    case Classic = 'classic';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic',
            self::Dark => 'Dark',
        };
    }

    public static function default(): self
    {
        return self::tryFrom((string) config('platform.default_theme')) ?? self::Classic;
    }
}
