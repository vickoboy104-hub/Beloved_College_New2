<?php

namespace Tests\Feature\Academics;

use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_term_class_subject_and_teacher_access_remain_connected(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $session = AcademicSession::query()->create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'promotion_pass_mark' => 40,
            'is_current' => true,
        ]);

        $term = Term::query()->create([
            'academic_session_id' => $session->id,
            'name' => 'First Term',
            'slug' => 'first-term',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-18',
            'is_current' => true,
        ]);

        $class = SchoolClass::query()->create([
            'name' => 'JSS 2',
            'slug' => 'jss-2-a',
            'section' => 'A',
            'class_teacher_id' => $teacher->id,
        ]);

        $subject = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MTH',
        ]);

        $assignment = TeacherSubjectAssignment::query()->create([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'assigned_by' => $admin->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($session->terms->contains($term));
        $this->assertTrue($term->academicSession->is($session));
        $this->assertSame('JSS 2 | A', $class->display_name);
        $this->assertTrue($class->classTeacher->is($teacher));
        $this->assertTrue($teacher->teacherSubjectAssignments->contains($assignment));
        $this->assertTrue($assignment->schoolClass->is($class));
        $this->assertTrue($assignment->subject->is($subject));
        $this->assertSame(1, TeacherSubjectAssignment::query()->active()->count());
    }
}
