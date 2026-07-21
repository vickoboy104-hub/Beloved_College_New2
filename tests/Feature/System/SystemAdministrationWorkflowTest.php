<?php

namespace Tests\Feature\System;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemTestEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SystemAdministrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_admin_can_open_system_and_principal_can_only_manage_communication(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $principal = User::factory()->role(UserRole::Principal)->create();
        Artisan::call('system:heartbeat', ['service' => 'scheduler']);

        $this->actingAs($admin)
            ->get($this->webUrl('/admin/system'))
            ->assertOk()
            ->assertSee('System Health and Settings')
            ->assertSee('Database')
            ->assertSee('Scheduler');

        $this->actingAs($principal)
            ->get($this->webUrl('/admin/communication'))
            ->assertOk()
            ->assertSee('Announcements and Alerts');

        $this->actingAs($principal)
            ->get($this->webUrl('/admin/system'))
            ->assertForbidden();
    }

    public function test_mail_password_is_encrypted_blank_update_preserves_it_and_test_delivery_is_available(): void
    {
        Notification::fake();
        $admin = User::factory()->role(UserRole::Admin)->create([
            'email' => 'admin@beloved.example',
        ]);

        $this->actingAs($admin)
            ->put($this->webUrl('/admin/system/mail'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.beloved.example',
                'mail_port' => 587,
                'mail_username' => 'mailer@beloved.example',
                'mail_password' => 'super-secret-mail-password',
                'mail_scheme' => 'smtp',
                'mail_timeout' => 20,
                'mail_from_address' => 'no-reply@beloved.example',
                'mail_from_name' => 'Beloved College',
            ])->assertSessionHasNoErrors();

        $stored = Setting::query()->where('key', 'mail_password')->value('value');
        $this->assertStringStartsWith('encrypted:', $stored);
        $this->assertStringNotContainsString('super-secret-mail-password', $stored);
        $this->assertSame('super-secret-mail-password', Setting::getValue('mail_password'));

        $this->actingAs($admin)
            ->put($this->webUrl('/admin/system/mail'), [
                'mail_mailer' => 'array',
                'mail_host' => null,
                'mail_port' => 587,
                'mail_username' => null,
                'mail_password' => '',
                'mail_scheme' => null,
                'mail_timeout' => 20,
                'mail_from_address' => 'no-reply@beloved.example',
                'mail_from_name' => 'Beloved College',
            ])->assertSessionHasNoErrors();
        $this->assertSame('super-secret-mail-password', Setting::getValue('mail_password'));

        $this->actingAs($admin)
            ->post($this->webUrl('/admin/system/mail/test'), [
                'test_email' => 'operations@beloved.example',
            ])->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(SystemTestEmailNotification::class);
    }

    public function test_audit_filters_render_and_failed_job_can_be_deleted(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        AuditLog::query()->create([
            'user_id' => $admin->id,
            'route' => 'web.admin.finance.index',
            'method' => 'GET',
            'path' => '/admin/finance',
            'action' => 'finance.review',
            'status_code' => 200,
            'ip_address' => '127.0.0.1',
            'metadata' => ['source' => 'test'],
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'failed-job-communication-test',
            'connection' => 'database',
            'queue' => 'notifications',
            'payload' => '{}',
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get($this->webUrl('/admin/system?section=audit&audit_action=finance'))
            ->assertOk()
            ->assertSee('finance.review')
            ->assertSee('/admin/finance');

        $this->actingAs($admin)
            ->delete($this->webUrl('/admin/system/failed-jobs/failed-job-communication-test'))
            ->assertRedirect();
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    private function webUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.web').$path;
    }
}
