<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\AccountSecurityNotification;
use App\Notifications\PasswordResetLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_request_is_private_and_uses_the_requesting_surface(): void
    {
        Notification::fake();
        $user = User::factory()->role(UserRole::Parent)->create([
            'email' => 'parent@beloved.example',
        ]);

        $this->post($this->appUrl('/forgot-password'), [
            'email' => $user->email,
        ])->assertSessionHas('status', 'If an active account uses that email address, a password reset link has been sent.');

        Notification::assertSentTo(
            $user,
            PasswordResetLinkNotification::class,
            fn (PasswordResetLinkNotification $notification) => $notification->surfacePrefix === 'app',
        );

        $this->post($this->appUrl('/forgot-password'), [
            'email' => 'unknown@beloved.example',
        ])->assertSessionHas('status', 'If an active account uses that email address, a password reset link has been sent.');
    }

    public function test_valid_reset_updates_password_revokes_sessions_and_records_security_event(): void
    {
        Notification::fake();
        $user = User::factory()->role(UserRole::Teacher)->create([
            'email' => 'teacher.reset@beloved.example',
            'password' => 'OldPassword1',
            'must_change_password' => true,
        ]);
        $token = Password::broker()->createToken($user);
        $this->insertSession('reset-session-one', $user);
        $this->insertSession('reset-session-two', $user);

        $this->post($this->webUrl('/reset-password'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewSecurePassword2',
            'password_confirmation' => 'NewSecurePassword2',
        ])->assertRedirect($this->webUrl('/login'));

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword2', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'password.reset',
            'severity' => 'critical',
        ]);
        Notification::assertSentTo($user, AccountSecurityNotification::class);
    }

    public function test_invalid_reset_token_does_not_change_password_or_revoke_sessions(): void
    {
        Notification::fake();
        $user = User::factory()->role(UserRole::Teacher)->create([
            'email' => 'invalid.reset@beloved.example',
            'password' => 'OriginalPassword1',
        ]);
        $this->insertSession('invalid-reset-session', $user);

        $this->post($this->webUrl('/reset-password'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'AnotherPassword2',
            'password_confirmation' => 'AnotherPassword2',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OriginalPassword1', $user->fresh()->password));
        $this->assertDatabaseHas('sessions', ['id' => 'invalid-reset-session']);
        $this->assertSame(0, SecurityEvent::query()->where('user_id', $user->id)->count());
    }

    private function insertSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Test Browser',
            'payload' => 'test-payload',
            'last_activity' => now()->timestamp,
        ]);
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
