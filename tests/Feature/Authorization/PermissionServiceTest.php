<?php

namespace Tests\Feature\Authorization;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Authorization\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_every_permission(): void
    {
        $user = User::factory()->role(UserRole::SuperAdmin)->create();
        $service = app(PermissionService::class);

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($service->allows($user, $permission));
        }
    }

    public function test_admin_controls_the_operational_platform_but_not_super_admin_accounts(): void
    {
        $user = User::factory()->role(UserRole::Admin)->create();
        $service = app(PermissionService::class);

        $this->assertTrue($service->allows($user, Permission::ManageThemes));
        $this->assertTrue($service->allows($user, Permission::ConfigurePaymentGateways));
        $this->assertTrue($service->allows($user, Permission::ManageUsers));
        $this->assertFalse($service->allows($user, Permission::ManageSuperAdmins));
    }

    public function test_principal_keeps_academic_control_without_theme_or_gateway_control(): void
    {
        $user = User::factory()->role(UserRole::Principal)->create();
        $service = app(PermissionService::class);

        $this->assertTrue($service->allows($user, Permission::ReviewReports));
        $this->assertTrue($service->allows($user, Permission::ProcessPromotions));
        $this->assertFalse($service->allows($user, Permission::ManageThemes));
        $this->assertFalse($service->allows($user, Permission::ConfigurePaymentGateways));
    }

    public function test_explicit_override_can_deny_a_default_role_permission(): void
    {
        $superAdmin = User::factory()->role(UserRole::SuperAdmin)->create();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $service = app(PermissionService::class);

        $service->setOverride(
            actor: $superAdmin,
            subject: $admin,
            permission: Permission::ManageThemes,
            allowed: false,
            reason: 'Theme management delegated to another administrator.',
        );

        $this->assertFalse($service->allows($admin->fresh(), Permission::ManageThemes));
    }

    public function test_admin_cannot_change_super_admin_permissions(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $superAdmin = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->expectException(AuthorizationException::class);

        app(PermissionService::class)->setOverride(
            actor: $admin,
            subject: $superAdmin,
            permission: Permission::ManageThemes,
            allowed: false,
        );
    }
}
