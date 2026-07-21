<?php

namespace Tests\Feature\Portal;

use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningPortalWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_workspaces_render_on_web_and_mobile_surfaces(): void
    {
        [$teacher] = $this->context();

        foreach (['web' => config('platform.hosts.web'), 'app' => config('platform.hosts.app')] as $surface => $host) {
            $this->actingAs($teacher)
                ->get("http://{$host}/teacher/learning")
                ->assertOk()
                ->assertSee('Learning and Assessment');

            $this->actingAs($teacher)
                ->get("http://{$host}/teacher/cbt")
                ->assertOk()
                ->assertSee('CBT Workspace');
        }
    }

    public function test_student_and_linked_parent_portals_render(): void
    {
        [$teacher, $student, $parent] = $this->context();

        $this->actingAs($student->user)
            ->get($this->appUrl('/portal'))
            ->assertOk()
            ->assertSee($student->user->fullName());

        $this->actingAs($parent)
            ->get($this->appUrl('/portal?student_id='.$student->id))
            ->assertOk()
            ->assertSee($student->admission_no);
    }

    public function test_report_administration_and_public_checker_forms_render(): void
    {
        $this->context();
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->get($this->webUrl('/admin/reports'))
            ->assertOk()
            ->assertSee('Student Reports');

        $this->get('http://'.config('platform.hosts.public').'/result-checker')
            ->assertOk()
            ->assertSee('Check a published result');
    }

    /**
     * @return array{User, Student, User}
     */
    private function context(): array
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $parent = User::factory()->role(UserRole::Parent)->create();
        $studentUser = User::factory()->role(UserRole::Student)->create();
        $session = AcademicSession::query()->firstOrCreate([
            'name' => '2026/2027',
        ], [
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'promotion_pass_mark' => 40,
            'is_current' => true,
        ]);
        $term = Term::query()->firstOrCreate([
            'academic_session_id' => $session->id,
            'slug' => 'first-term-portal',
        ], [
            'name' => 'First Term',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-18',
            'is_current' => true,
        ]);
        $class = SchoolClass::query()->firstOrCreate([
            'slug' => 'jss-1-portal',
        ], [
            'name' => 'JSS 1',
            'section' => 'A',
        ]);
        $subject = Subject::query()->firstOrCreate([
            'code' => 'ENG-PORTAL',
        ], [
            'name' => 'English Language',
        ]);
        TeacherSubjectAssignment::query()->firstOrCreate([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
        ], [
            'is_active' => true,
            'assigned_at' => now(),
        ]);
        $student = Student::query()->firstOrCreate([
            'admission_no' => 'ADM-26-PORTAL',
        ], [
            'user_id' => $studentUser->id,
            'parent_user_id' => $parent->id,
            'school_class_id' => $class->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        return [$teacher, $student, $parent];
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
