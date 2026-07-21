<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AccountSecurityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SessionSecurityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_review_and_revoke_only_owned_non_current_sessions(): void
    {
        $user = User::factory()->role(UserRole::Teacher)->create();
        $other = User::factory()->role(UserRole::Teacher)->create();
        $this->insertSession('owned-mobile-session', $user, '10.0.0.1', 'Mozilla/5.0 Android Chrome/130.0');
        $this->insertSession('other-user-session', $other, '10.0.0.2', 'Mozilla/5.0 Firefox/130.0');

        $this->actingAs($user)
            ->get($this->webUrl('/security'))
            ->assertOk()
            ->assertSee('Active devices')
            ->assertSee('10.0.0.1')
            ->assertDontSee('10.0.0.2');

        $this->actingAs($user)
            ->delete($this->webUrl('/security/sessions/other-user-session'))
            ->assertNotFound();
        $this->assertDatabaseHas('sessions', ['id' => 'other-user-session']);

        $this->actingAs($user)
            ->delete($this->webUrl('/security/sessions/owned-mobile-session'))
            ->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'owned-mobile-session']);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'session.revoked',
        ]);
    }

    public function test_password_change_revokes_other_sessions_and_records_alert(): void
    {
        Notification::fake();
        $user = User::factory()->role(UserRole::Admin)->create([
            'password' => 'password',
        ]);
        $this->insertSession('old-admin-session-one', $user, '10.0.0.3', 'Old Browser One');
        $this->insertSession('old-admin-session-two', $user, '10.0.0.4', 'Old Browser Two');

        $this->actingAs($user)
            ->put($this->webUrl('/security/password'), [
                'current_password' => 'password',
                'password' => 'SecurePassword2',
                'password_confirmation' => 'SecurePassword2',
            ])->assertSessionHas('status', 'Password updated and other sessions signed out.');

        $user->refresh();
        $this->assertTrue(Hash::check('SecurePassword2', $user->password));
        $this->assertNotNull($user->password_changed_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-admin-session-one']);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-admin-session-two']);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'password.changed',
            'severity' => 'critical',
        ]);
        Notification::assertSentTo($user, AccountSecurityNotification::class);
    }

    public function test_successful_login_updates_history_and_records_security_event(): void
    {
        Notification::fake();
        $user = User::factory()->role(UserRole::Teacher)->create([
            'email' => 'login.history@beloved.example',
            'password' => 'password',
        ]);

        $this->post($this->webUrl('/login'), [
            'login' => $user->email,
            'password' => 'password',
            'audience' => 'staff',
        ], [
            'REMOTE_ADDR' => '10.20.30.40',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Security Test',
        ])->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('10.20.30.40', $user->last_login_ip);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'login.succeeded',
            'ip_address' => '10.20.30.40',
        ]);
    }

    private function insertSession(string $id, User $user, string $ip, string $agent): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => $agent,
            'payload' => 'test-payload',
            'last_activity' => now()->timestamp,
        ]);
    }

    private function webUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.web').$path;
    }
}
