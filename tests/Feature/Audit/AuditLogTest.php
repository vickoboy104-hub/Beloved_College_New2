<?php

namespace Tests\Feature\Audit;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_write_is_logged_without_request_secrets(): void
    {
        $user = User::factory()->role(UserRole::Teacher)->create();

        $this->actingAs($user)
            ->put('http://'.config('platform.hosts.web').'/password/change', [
                'password' => 'private-new-password',
                'password_confirmation' => 'private-new-password',
            ])
            ->assertRedirect(route('web.dashboard'));

        $log = AuditLog::query()->firstOrFail();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('web.password-change.update', $log->route);
        $this->assertSame('PUT', $log->method);
        $this->assertSame(302, $log->status_code);
        $this->assertSame(['successful' => true], $log->metadata);
        $this->assertFalse(array_key_exists('password', $log->getAttributes()));
        $this->assertStringNotContainsString('private-new-password', json_encode($log->toArray()));
    }
}
