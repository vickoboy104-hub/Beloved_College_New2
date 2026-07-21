<?php

namespace Tests\Feature\Cbt;

use App\Enums\AssessmentType;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CbtAnswer;
use App\Models\CbtAttempt;
use App\Models\CbtQuestion;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CbtScoreSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_attempt_waits_for_theory_and_then_becomes_graded(): void
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
            'name' => 'JSS 3',
            'slug' => 'jss-3-a',
            'section' => 'A',
        ]);

        $subject = Subject::query()->create([
            'name' => 'Basic Science',
            'code' => 'BSC',
        ]);

        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'admission_no' => 'BC/2026/CBT01',
            'school_class_id' => $class->id,
            'academic_session_id' => $session->id,
            'status' => 'active',
        ]);

        $assessment = Assessment::query()->create([
            'teacher_id' => $teacher->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'title' => 'First Term CBT',
            'type' => AssessmentType::Test,
            'is_cbt' => true,
            'total_score' => 15,
            'cbt_duration_minutes' => 30,
            'cbt_is_active' => true,
            'cbt_show_results' => false,
        ]);

        $objective = CbtQuestion::query()->create([
            'assessment_id' => $assessment->id,
            'question_type' => 'objective',
            'prompt' => 'Which planet is known as the red planet?',
            'points' => 5,
            'sort_order' => 1,
        ]);

        $theory = CbtQuestion::query()->create([
            'assessment_id' => $assessment->id,
            'question_type' => 'theory',
            'prompt' => 'Explain photosynthesis.',
            'points' => 10,
            'sort_order' => 2,
        ]);

        $attempt = CbtAttempt::query()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(15),
            'expires_at' => now()->addMinutes(15),
            'submitted_at' => now(),
        ]);

        CbtAnswer::query()->create([
            'cbt_attempt_id' => $attempt->id,
            'cbt_question_id' => $objective->id,
            'is_correct' => true,
            'awarded_score' => 5,
            'graded_at' => now(),
        ]);

        $theoryAnswer = CbtAnswer::query()->create([
            'cbt_attempt_id' => $attempt->id,
            'cbt_question_id' => $theory->id,
            'answer_text' => 'Plants use light to make food.',
            'awarded_score' => null,
            'graded_at' => null,
        ]);

        $attempt->syncScores();

        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame('5.00', $attempt->objective_score);
        $this->assertSame('0.00', $attempt->theory_score);
        $this->assertSame('5.00', $attempt->total_score);
        $this->assertSame('5.00', AssessmentResult::query()->firstOrFail()->score);

        $theoryAnswer->update([
            'awarded_score' => 7,
            'graded_at' => now(),
        ]);

        $attempt->syncScores();

        $attempt->refresh();
        $result = AssessmentResult::query()->firstOrFail();

        $this->assertSame('graded', $attempt->status);
        $this->assertSame('5.00', $attempt->objective_score);
        $this->assertSame('7.00', $attempt->theory_score);
        $this->assertSame('12.00', $attempt->total_score);
        $this->assertSame('12.00', $result->score);
        $this->assertSame('CBT graded.', $result->remark);
    }
}
