<?php

namespace Tests\Feature\Migration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdentitySecuritySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_security_schema_exists_without_replacing_legacy_auth_tables(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'email',
            'email_verified_at',
            'password',
            'remember_token',
            'must_change_password',
            'password_changed_at',
            'last_login_at',
            'last_login_ip',
        ]));
        $this->assertTrue(Schema::hasColumns('password_reset_tokens', [
            'email', 'token', 'created_at',
        ]));
        $this->assertTrue(Schema::hasColumns('sessions', [
            'id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity',
        ]));
        $this->assertTrue(Schema::hasColumns('security_events', [
            'user_id', 'event', 'severity', 'ip_address', 'user_agent', 'metadata', 'occurred_at',
        ]));
    }
}
