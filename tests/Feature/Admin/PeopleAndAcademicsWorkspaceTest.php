<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeopleAndAcademicsWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_all_people_and_academic_workspaces(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        foreach ([
            '/admin/students',
            '/admin/staff',
            '/admin/academics',
            '/admin/teacher-access',
        ] as $path) {
            $this->actingAs($admin)
                ->get($this->webUrl($path))
                ->assertOk();
        }
    }

    public function test_principal_can_open_academic_and_people_workspaces(): void
    {
        $principal = User::factory()->role(UserRole::Principal)->create();

        foreach ([
            '/admin/students',
            '/admin/staff',
            '/admin/academics',
            '/admin/teacher-access',
        ] as $path) {
            $this->actingAs($principal)
                ->get($this->webUrl($path))
                ->assertOk();
        }
    }

    public function test_teacher_cannot_open_student_or_staff_administration(): void
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $this->actingAs($teacher)
            ->get($this->webUrl('/admin/students'))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->get($this->webUrl('/admin/staff'))
            ->assertForbidden();
    }

    private function webUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.web').$path;
    }
}
