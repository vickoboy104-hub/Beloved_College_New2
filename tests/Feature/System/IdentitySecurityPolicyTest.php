<?php

namespace Tests\Feature\System;

use App\Enums\UserRole;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentitySecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_admin_can_update_identity_policy_and_principal_cannot(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $principal = User::factory()->role(UserRole::Principal)->create();

        $this->actingAs($admin)
            ->put($this->webUrl('/admin/system/identity'), [
                'email_verification_required' => '1',
                'security_email_alerts_enabled' => '1',
                'security_login_alerts_enabled' => '1',
            ])->assertSessionHas('status', 'Identity security policy updated.');

        $this->assertSame('1', Setting::getValue('email_verification_required'));
        $this->assertSame('1', Setting::getValue('security_email_alerts_enabled'));
        $this->assertSame('1', Setting::getValue('security_login_alerts_enabled'));

        $this->actingAs($principal)
            ->put($this->webUrl('/admin/system/identity'), [
                'email_verification_required' => '0',
            ])->assertForbidden();
        $this->assertSame('1', Setting::getValue('email_verification_required'));
    }

    public function test_identity_administration_renders_readiness_counts_and_security_events(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create([
            'email' => 'identity.admin@beloved.example',
            'email_verified_at' => now(),
        ]);
        User::factory()->role(UserRole::Teacher)->create([
            'email' => 'unverified.teacher@beloved.example',
            'email_verified_at' => null,
        ]);
        User::factory()->role(UserRole::Student)->create([
            'email' => null,
            'email_verified_at' => null,
        ]);
        SecurityEvent::query()->create([
            'user_id' => $admin->id,
            'event' => 'password.changed',
            'severity' => 'critical',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Identity Test Browser',
            'metadata' => ['source' => 'test'],
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get($this->webUrl('/admin/system?section=identity'))
            ->assertOk()
            ->assertSee('Recovery, verification and security alerts')
            ->assertSee('Security ledger')
            ->assertSee('Password Changed')
            ->assertSee('Unverified')
            ->assertSee('Without email');
    }

    private function webUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.web').$path;
    }
}
