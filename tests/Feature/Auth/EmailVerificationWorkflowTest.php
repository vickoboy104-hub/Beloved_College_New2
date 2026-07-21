<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AccountSecurityNotification;
use App\Notifications\VerifyEmailAddressNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_user_can_request_and_complete_surface_aware_signed_verification(): void
    {
        Notification::fake();
        $user = User::factory()->role(UserRole::Parent)->create([
            'email' => 'verify.parent@beloved.example',
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->post($this->appUrl('/email/verification-notification'))
            ->assertSessionHas('status', 'A new verification link has been sent.');

        Notification::assertSentTo(
            $user,
            VerifyEmailAddressNotification::class,
            fn (VerifyEmailAddressNotification $notification) => $notification->surfacePrefix === 'app',
        );

        $verificationUrl = URL::temporarySignedRoute(
            'app.verification.verify',
            now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertRedirect($this->appUrl('/dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'email.verified',
        ]);
        Notification::assertSentTo($user, AccountSecurityNotification::class);
    }

    public function test_verification_policy_redirects_only_unverified_users_with_email(): void
    {
        Setting::setMany(['email_verification_required' => '1'], 'identity');
        $unverified = User::factory()->role(UserRole::Teacher)->create([
            'email' => 'unverified@beloved.example',
            'email_verified_at' => null,
            'must_change_password' => false,
        ]);
        $withoutEmail = User::factory()->role(UserRole::Teacher)->create([
            'email' => null,
            'email_verified_at' => null,
            'must_change_password' => false,
        ]);

        $this->actingAs($unverified)
            ->get($this->webUrl('/dashboard'))
            ->assertRedirect($this->webUrl('/email/verify'));

        $this->actingAs($withoutEmail)
            ->get($this->webUrl('/dashboard'))
            ->assertOk();
    }

    public function test_temporary_password_replacement_precedes_email_verification(): void
    {
        Setting::setMany(['email_verification_required' => '1'], 'identity');
        $user = User::factory()->role(UserRole::Student)->create([
            'email' => 'temporary@beloved.example',
            'email_verified_at' => null,
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get($this->appUrl('/dashboard'))
            ->assertRedirect($this->appUrl('/password/change'));
    }

    public function test_invalid_signature_cannot_verify_email(): void
    {
        $user = User::factory()->role(UserRole::Teacher)->create([
            'email' => 'invalid.signature@beloved.example',
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->get($this->webUrl('/email/verify/'.$user->id.'/'.sha1($user->email)))
            ->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
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
