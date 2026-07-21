<?php

namespace Tests\Feature\Cbt;

use App\Enums\AssessmentType;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\AssessmentResult;
use App\Models\CbtAnswer;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Cbt\CbtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompleteCbtWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_objective_and_theory_attempt_survives_global_shutdown_and_finishes_graded(): void
    {
        Carbon::setTestNow('2026-10-01 09:00:00');
        [$admin, $teacher, $student, $class, $subject, $term] = $this->context();
        $service = app(CbtService::class);
        $assessment = $service->createAssessment($teacher, [
            'term_id' => $term->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => 'Integrated Science CBT',
            'type' => AssessmentType::Test,
            'cbt_duration_minutes' => 1,
            'cbt_starts_at' => now()->subMinute(),
            'cbt_ends_at' => now()->addMinutes(10),
            'cbt_show_results' => true,
        ]);
        $objective = $service->addQuestion($teacher, $assessment, [
            'question_type' => 'objective',
            'prompt' => 'Which organ pumps blood?',
            'points' => 5,
            'options' => [
                ['text' => 'Heart', 'is_correct' => true],
                ['text' => 'Lung', 'is_correct' => false],
            ],
        ]);
        $theory = $service->addQuestion($teacher, $assessment, [
            'question_type' => 'theory',
            'prompt' => 'Explain respiration.',
            'points' => 10,
            'theory_sample_answer' => 'Respiration releases energy from food.',
        ]);
        $service->setAssessmentActive($admin, $assessment, true);
        $attempt = $service->startAttempt($student, $assessment->fresh());
        $correctOption = $objective->options()->where('is_correct', true)->firstOrFail();

        $service->setGlobalEnabled($admin, false);
        Carbon::setTestNow('2026-10-01 09:01:01');

        $attempt = $service->submitAttempt($student, $assessment->fresh(), [
            $objective->id => $correctOption->id,
            $theory->id => 'Respiration is the process by which energy is released from food.',
        ]);

        $this->assertSame('submitted', $attempt->status);
        $this->assertSame('5.00', $attempt->objective_score);
        $this->assertSame('0.00', $attempt->theory_score);
        $this->assertSame('5.00', AssessmentResult::query()->firstOrFail()->score);

        $theoryAnswer = CbtAnswer::query()
            ->where('cbt_attempt_id', $attempt->id)
            ->where('cbt_question_id', $theory->id)
            ->firstOrFail();
        $service->gradeTheoryAnswer($teacher, $theoryAnswer, [
            'score' => 8,
            'feedback' => 'Good explanation.',
        ]);

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertSame('8.00', $attempt->theory_score);
        $this->assertSame('13.00', $attempt->total_score);
        $this->assertSame('13.00', AssessmentResult::query()->firstOrFail()->score);

        $this->expectException(ValidationException::class);
        $service->addQuestion($teacher, $assessment, [
            'question_type' => 'theory',
            'prompt' => 'This late question must be rejected.',
            'points' => 1,
        ]);
    }

    /**
     * @return array{User, User, Student, SchoolClass, Subject, Term}
     */
    private function context(): array
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
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
            'slug' => 'first-term-complete-cbt',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-18',
            'is_current' => true,
        ]);
        $class = SchoolClass::query()->create([
            'name' => 'JSS 3',
            'slug' => 'jss-3-complete-cbt',
            'section' => 'A',
        ]);
        $subject = Subject::query()->create([
            'name' => 'Integrated Science',
            'code' => 'ISC-CBT',
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
            'admission_no' => 'ADM-26-CBT-COMPLETE',
            'school_class_id' => $class->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        return [$admin, $teacher, $student, $class, $subject, $term];
    }
}
