<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\FeeItem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeopleWorkflowHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_student_and_receives_one_time_credentials(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $session = AcademicSession::query()->create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'promotion_pass_mark' => 40,
            'is_current' => true,
        ]);
        $class = SchoolClass::query()->create([
            'name' => 'JSS 1',
            'slug' => 'jss-1-a',
            'section' => 'A',
        ]);
        FeeItem::query()->create([
            'name' => 'Registration',
            'academic_session_id' => $session->id,
            'school_class_id' => $class->id,
            'amount' => 15000,
            'is_mandatory' => true,
        ]);

        $response = $this->actingAs($admin)
            ->post($this->webUrl('/admin/students'), [
                'first_name' => 'Chioma',
                'last_name' => 'Eze',
                'school_class_id' => $class->id,
                'parent_name' => 'Mrs Eze',
                'parent_email' => 'mrs.eze@example.com',
            ]);

        $student = Student::query()->firstOrFail();

        $response
            ->assertRedirect(route('web.admin.students.show', $student))
            ->assertSessionHas('generated_credentials', fn (array $credentials) => count($credentials) === 2);

        $this->assertSame(1, $student->feeInvoices()->count());
        $this->assertTrue($student->user->must_change_password);
    }

    public function test_principal_cannot_create_admin_through_staff_route(): void
    {
        $principal = User::factory()->role(UserRole::Principal)->create();

        $this->actingAs($principal)
            ->post($this->webUrl('/admin/staff'), [
                'first_name' => 'Unauthorized',
                'last_name' => 'Administrator',
                'email' => 'unauthorized-admin@example.com',
                'role' => UserRole::Admin->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'unauthorized-admin@example.com',
        ]);
    }

    private function webUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.web').$path;
    }
}
