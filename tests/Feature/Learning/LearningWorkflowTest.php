<?php

namespace Tests\Feature\Learning;

use App\Enums\AssessmentType;
use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Learning\AssignmentSubmissionService;
use App\Services\Learning\AttendanceService;
use App\Services\Learning\LearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_teacher_can_publish_create_assess_record_and_grade_with_exact_access(): void
    {
        [$teacher, $class, $subject, $term, $student] = $this->context();
        $learning = app(LearningService::class);

        $lesson = $learning->publishLesson($teacher, [
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => 'Introduction to Algebra',
            'summary' => 'Variables and expressions.',
            'body' => 'A variable represents an unknown value.',
            'note_images' => [UploadedFile::fake()->image('algebra.png')],
        ]);

        Storage::disk('local')->assertExists($lesson->note_images[0]);

        $assignment = $learning->createAssignment($teacher, [
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => 'Algebra Practice',
            'instructions' => 'Solve all questions.',
            'due_date' => now()->addDay(),
            'total_score' => 20,
            'status' => 'published',
            'allowed_submission_types' => ['text', 'image'],
            'max_submission_files' => 2,
        ]);

        $submission = app(AssignmentSubmissionService::class)->submit($student, $assignment, [
            'content' => 'My written solution.',
            'files' => [UploadedFile::fake()->image('solution.jpg')],
        ]);

        Storage::disk('local')->assertExists($submission->attachment_paths[0]);

        app(AssignmentSubmissionService::class)->grade($teacher, $submission, [
            'score' => 18,
            'feedback' => 'Very good work.',
        ]);

        $this->assertSame('18.00', $submission->fresh()->score);
        $this->assertSame($teacher->id, $submission->fresh()->graded_by);

        $assessment = $learning->createAssessment($teacher, [
            'term_id' => $term->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => 'Algebra Test',
            'type' => AssessmentType::Test,
            'total_score' => 50,
        ]);
        $result = $learning->recordResult($teacher, $assessment, $student, ['score' => 40]);

        $this->assertSame('A', $result->grade);
        $this->assertSame('Excellent', $result->remark);
    }

    public function test_bulk_attendance_updates_the_class_register(): void
    {
        [$teacher, $class, $subject, $term, $student] = $this->context();
        $secondUser = User::factory()->role(UserRole::Student)->create();
        $secondStudent = Student::query()->create([
            'user_id' => $secondUser->id,
            'admission_no' => 'ADM-26-LEARN2',
            'school_class_id' => $class->id,
            'academic_session_id' => $term->academic_session_id,
            'status' => 'active',
        ]);

        $records = app(AttendanceService::class)->recordBulk(
            $teacher,
            $class->id,
            '2026-10-01',
            [
                $student->id => ['status' => AttendanceStatus::Present->value],
                $secondStudent->id => ['status' => AttendanceStatus::Late->value, 'note' => 'Transport delay'],
            ],
        );

        $this->assertCount(2, $records);
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $secondStudent->id,
            'status' => AttendanceStatus::Late->value,
            'note' => 'Transport delay',
        ]);
    }

    /**
     * @return array{User, SchoolClass, Subject, Term, Student}
     */
    private function context(): array
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $studentUser = User::factory()->role(UserRole::Student)->create();
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
            'name' => 'JSS 1',
            'slug' => 'jss-1-learning',
            'section' => 'A',
        ]);
        $subject = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MTH-L',
        ]);
        TeacherSubjectAssignment::query()->create([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'is_active' => true,
            'assigned_at' => now(),
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'admission_no' => 'ADM-26-LEARN1',
            'school_class_id' => $class->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        return [$teacher, $class, $subject, $term, $student];
    }
}
