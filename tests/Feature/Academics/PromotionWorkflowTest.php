<?php

namespace Tests\Feature\Academics;

use App\Enums\AssessmentType;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\FeeItem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPromotion;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Academics\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommended_promotion_moves_student_records_history_and_generates_fees(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $studentUser = User::factory()->role(UserRole::Student)->create();

        $source = AcademicSession::query()->create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-07-31',
            'promotion_pass_mark' => 40,
            'is_current' => false,
            'closed_at' => now(),
            'closed_by' => $admin->id,
        ]);
        $target = AcademicSession::query()->create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'promotion_pass_mark' => 40,
            'is_current' => true,
        ]);
        $term = Term::query()->create([
            'academic_session_id' => $source->id,
            'name' => 'Third Term',
            'slug' => 'third-term',
            'start_date' => '2026-04-01',
            'end_date' => '2026-07-31',
            'is_current' => false,
        ]);
        $fromClass = SchoolClass::query()->create([
            'name' => 'JSS 1',
            'slug' => 'jss-1-a',
            'section' => 'A',
        ]);
        $toClass = SchoolClass::query()->create([
            'name' => 'JSS 2',
            'slug' => 'jss-2-a',
            'section' => 'A',
        ]);
        $subject = Subject::query()->create([
            'name' => 'Mathematics',
            'code' => 'MTH',
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'admission_no' => 'ADM-26-PROMO1',
            'school_class_id' => $fromClass->id,
            'academic_session_id' => $source->id,
            'status' => 'active',
        ]);
        $assessment = Assessment::query()->create([
            'teacher_id' => $teacher->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'school_class_id' => $fromClass->id,
            'title' => 'Mathematics Examination',
            'type' => AssessmentType::Exam,
            'is_cbt' => false,
            'total_score' => 100,
        ]);
        AssessmentResult::query()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => 80,
            'grade' => 'A',
            'remark' => 'Excellent',
        ]);
        $feeItem = FeeItem::query()->create([
            'name' => 'JSS 2 Tuition',
            'academic_session_id' => $target->id,
            'school_class_id' => $toClass->id,
            'amount' => 75000,
            'is_mandatory' => true,
        ]);

        $preview = app(PromotionService::class)->buildPromotionPreview($source);

        $this->assertCount(1, $preview);
        $this->assertSame('promote', $preview->first()['recommended_status']);
        $this->assertTrue($preview->first()['recommended_next_class']->is($toClass));
        $this->assertSame(80.0, $preview->first()['overall_percentage']);

        $counts = app(PromotionService::class)->process($admin, $source, $target);

        $student->refresh();
        $this->assertSame(['promoted' => 1, 'repeated' => 0], $counts);
        $this->assertSame($target->id, $student->academic_session_id);
        $this->assertSame($toClass->id, $student->school_class_id);

        $promotion = StudentPromotion::query()->firstOrFail();
        $this->assertSame('promote', $promotion->promotion_status);
        $this->assertSame('80.00', $promotion->overall_percentage);
        $this->assertSame($admin->id, $promotion->approved_by);

        $invoice = $student->feeInvoices()->firstOrFail();
        $this->assertSame($feeItem->id, $invoice->fee_item_id);
        $this->assertSame('75000.00', $invoice->balance);
    }
}
