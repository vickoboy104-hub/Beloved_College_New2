<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_password_user_is_redirected_before_dashboard_access(): void
    {
        $user = User::factory()
            ->role(UserRole::Student)
            ->temporaryPassword()
            ->create();

        $this->actingAs($user)
            ->get($this->appUrl('/dashboard'))
            ->assertRedirect(route('app.password-change.edit'));
    }

    public function test_user_can_replace_temporary_password(): void
    {
        $user = User::factory()
            ->role(UserRole::Teacher)
            ->temporaryPassword()
            ->create();

        $response = $this->actingAs($user)
            ->put($this->webUrl('/password/change'), [
                'password' => 'new-password-2026',
                'password_confirmation' => 'new-password-2026',
            ]);

        $response->assertRedirect(route('web.dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('new-password-2026', $user->password));
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
