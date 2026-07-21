<?php

namespace Tests\Feature\Website;

use App\Enums\ThemeMode;
use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\ThemeRevision;
use App\Models\User;
use App\Services\Website\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ThemeManagerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_admin_can_publish_and_rollback_classic_theme_revisions(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $service = app(ThemeService::class);
        $firstTokens = $service->defaults(ThemeMode::Classic);
        $firstTokens['primary'] = '#123f92';
        $first = $service->publish($admin, ThemeMode::Classic, $firstTokens, 'First approved Classic theme.');

        $secondTokens = $firstTokens;
        $secondTokens['primary'] = '#1b4fb0';
        $second = $service->publish($admin, ThemeMode::Classic, $secondTokens, 'Refined primary blue.');

        $this->assertFalse($first->fresh()->is_published);
        $this->assertTrue($second->fresh()->is_published);
        $this->assertSame('#1b4fb0', $service->tokens(ThemeMode::Classic)['primary']);

        $rollback = $service->rollback($admin, $first);
        $this->assertTrue($rollback->is_published);
        $this->assertSame('#123f92', $service->tokens(ThemeMode::Classic)['primary']);
        $this->assertSame(3, ThemeRevision::query()->count());
    }

    public function test_inaccessible_colour_pair_is_rejected(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $tokens = app(ThemeService::class)->defaults(ThemeMode::Classic);
        $tokens['text'] = '#ffffff';
        $tokens['page'] = '#ffffff';

        $this->expectException(ValidationException::class);
        app(ThemeService::class)->publish($admin, ThemeMode::Classic, $tokens);
    }

    public function test_admin_controls_whether_users_may_select_classic_or_dark(): void
    {
        $student = User::factory()->role(UserRole::Student)->create();
        Setting::setMany([
            'theme_default_mode' => 'classic',
            'theme_allow_user_selection' => '1',
        ], 'theme');

        $this->actingAs($student)
            ->put($this->appUrl('/theme-preference'), ['preferred_theme' => 'dark'])
            ->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertSame(ThemeMode::Dark, $student->preferred_theme);
        $this->assertSame(ThemeMode::Dark, $student->effectiveTheme());

        Setting::setMany([
            'theme_default_mode' => 'classic',
            'theme_allow_user_selection' => '0',
        ], 'theme');

        $this->actingAs($student)
            ->put($this->appUrl('/theme-preference'), ['preferred_theme' => 'classic'])
            ->assertForbidden();
        $this->assertSame(ThemeMode::Classic, $student->fresh()->effectiveTheme());
    }

    public function test_admin_theme_workspace_renders_and_principal_is_forbidden(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $principal = User::factory()->role(UserRole::Principal)->create();

        $this->actingAs($admin)
            ->get($this->webUrl('/admin/website/themes'))
            ->assertOk()
            ->assertSee('Classic and Dark Themes')
            ->assertSee('semantic tokens');

        $this->actingAs($principal)
            ->get($this->webUrl('/admin/website/themes'))
            ->assertForbidden();
    }

    private function webUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.web').$path;
    }

    private function appUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.app').$path;
    }
}
