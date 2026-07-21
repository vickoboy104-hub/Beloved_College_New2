<?php

namespace Tests\Feature;

use App\Enums\ThemeMode;
use Tests\TestCase;

class ThemeContractTest extends TestCase
{
    public function test_the_platform_exposes_exactly_classic_and_dark_themes(): void
    {
        $this->assertSame(['classic', 'dark'], config('platform.themes'));
        $this->assertSame(
            ['classic', 'dark'],
            array_map(fn (ThemeMode $theme) => $theme->value, ThemeMode::cases()),
        );
    }

    public function test_invalid_default_theme_falls_back_to_classic(): void
    {
        config()->set('platform.default_theme', 'unsupported-theme');

        $this->assertSame(ThemeMode::Classic, ThemeMode::default());
    }

    public function test_dark_can_be_selected_as_the_default_theme(): void
    {
        config()->set('platform.default_theme', 'dark');

        $this->assertSame(ThemeMode::Dark, ThemeMode::default());
    }
}
