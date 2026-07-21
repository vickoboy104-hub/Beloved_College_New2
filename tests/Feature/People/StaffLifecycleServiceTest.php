<?php

namespace Tests\Feature\People;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\People\StaffService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_principal_can_create_teacher_but_cannot_create_admin(): void
    {
        $principal = User::factory()->role(UserRole::Principal)->create();
        $service = app(StaffService::class);

        $result = $service->create($principal, [
            'first_name' => 'Musa',
            'last_name' => 'Bello',
            'email' => 'musa.bello@example.com',
            'role' => UserRole::Teacher->value,
            'department' => 'Science',
        ]);

        $this->assertSame(UserRole::Teacher, $result['profile']->user->role);
        $this->assertTrue($result['profile']->user->must_change_password);
        $this->assertSame('staff', $result['credential']->audience);

        $this->expectException(AuthorizationException::class);

        $service->create($principal, [
            'first_name' => 'Elevated',
            'last_name' => 'Account',
            'email' => 'admin-attempt@example.com',
            'role' => UserRole::Admin->value,
        ]);
    }

    public function test_admin_can_create_admin_but_not_super_admin(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $service = app(StaffService::class);

        $result = $service->create($admin, [
            'first_name' => 'Second',
            'last_name' => 'Administrator',
            'email' => 'second-admin@example.com',
            'role' => UserRole::Admin->value,
        ]);

        $this->assertSame(UserRole::Admin, $result['profile']->user->role);

        $this->expectException(AuthorizationException::class);

        $service->create($admin, [
            'first_name' => 'Super',
            'last_name' => 'Attempt',
            'email' => 'super-attempt@example.com',
            'role' => UserRole::SuperAdmin->value,
        ]);
    }

    public function test_staff_archive_and_restore_are_reversible(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $service = app(StaffService::class);
        $profile = $service->create($admin, [
            'first_name' => 'Grace',
            'last_name' => 'Mensah',
            'email' => 'grace.mensah@example.com',
            'role' => UserRole::Accountant->value,
            'salary' => 150000,
        ])['profile'];

        $service->archive($admin, $profile, 'Employment ended.');
        $profile->refresh();

        $this->assertTrue($profile->isArchived());
        $this->assertTrue($profile->user->isArchived());
        $this->assertDatabaseHas('staff_profiles', ['id' => $profile->id]);

        $service->restore($admin, $profile);
        $profile->refresh();

        $this->assertFalse($profile->isArchived());
        $this->assertFalse($profile->user->isArchived());
        $this->assertSame('active', $profile->status);
    }
}
