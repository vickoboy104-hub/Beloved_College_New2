<?php

namespace Tests\Feature\Migration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommunicationSystemSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_and_operational_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumns('notifications', [
            'id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at',
        ]));
        $this->assertTrue(Schema::hasColumns('announcement_deliveries', [
            'announcement_id', 'user_id', 'channels', 'status', 'queued_at', 'delivered_at', 'failed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('system_heartbeats', [
            'service', 'status', 'last_seen_at', 'metadata',
        ]));
        $this->assertTrue(Schema::hasColumns('announcements', [
            'priority', 'audience_mode', 'role_targets', 'class_ids', 'user_ids',
            'portal_enabled', 'email_enabled', 'starts_at', 'expires_at', 'dispatched_at', 'status',
        ]));
        $this->assertTrue(Schema::hasColumn('attendance_records', 'absence_notified_at'));
    }
}
