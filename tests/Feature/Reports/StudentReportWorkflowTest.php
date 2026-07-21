<?php

namespace Tests\Feature\Reports;

use App\Enums\AssessmentType;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Reports\StudentReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudentReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_compile_positions_publish_and_validate_public_checker_pin(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        [$session, $term, $class, $subject] = $this->academicContext();
        $first = $this->student('ADM-26-REPORT1', $class, $session);
        $second = $this->student('ADM-26-REPORT2', $class, $session);
        $assessment = Assessment::query()->create([
            'teacher_id' => $teacher->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'title' => 'Mathematics Exam',
            'type' => AssessmentType::Exam,
            'is_cbt' => false,
            'total_score' => 100,
        ]);
        AssessmentResult::query()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $first->id,
            'score' => 80,
            'grade' => 'A',
        ]);
        AssessmentResult::query()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $second->id,
            'score' => 60,
            'grade' => 'B',
        ]);

        $service = app(StudentReportService::class);
        $firstReport = $service->compile($first, $term);
        $secondReport = $service->compile($second, $term);

        $this->assertSame('80.00', $firstReport->average_score);
        $this->assertSame('A', $firstReport->overall_grade);
        $this->assertSame(1, $firstReport->class_position);
        $this->assertSame(2, $secondReport->class_position);

        $service->updateDetails($admin, $firstReport, [
            'days_school_open' => 60,
            'days_present' => 58,
            'days_absent' => 2,
            'character_traits' => ['conduct' => 'Excellent'],
            'principal_remark' => 'Excellent performance.',
        ]);
        $publication = $service->publish($admin, $firstReport, true, true, '246810');

        $publication->report->refresh();
        $this->assertTrue($publication->report->portal_enabled);
        $this->assertTrue($publication->report->checker_enabled);
        $this->assertTrue(Hash::check('246810', $publication->report->checker_pin_hash));
        $this->assertSame($publication->report->id, $service->lookup('ADM-26-REPORT1', $term, '246810')->id);

        $this->expectException(ValidationException::class);
        $service->lookup('ADM-26-REPORT1', $term, 'wrong-pin');
    }

    /**
     * @return array{AcademicSession, Term, SchoolClass, Subject}
     */
    private function academicContext(): array
    {
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
            'slug' => 'first-term-report',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-18',
            'is_current' => true,
        ]);
        $class = SchoolClass::query()->create([
            'name' => 'JSS 2',
            'slug' => 'jss-2-report',
            'section' => 'A',
        ]);
        $subject = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MTH-R',
        ]);

        return [$session, $term, $class, $subject];
    }

    private function student(string $admissionNumber, SchoolClass $class, AcademicSession $session): Student
    {
        $user = User::factory()->role(UserRole::Student)->create();

        return Student::query()->create([
            'user_id' => $user->id,
            'admission_no' => $admissionNumber,
            'school_class_id' => $class->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);
    }
}
