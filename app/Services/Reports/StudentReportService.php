<?php

namespace App\Services\Reports;

use App\Data\ReportPublicationResult;
use App\Enums\AssessmentType;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\Student;
use App\Models\StudentTermReport;
use App\Models\Term;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StudentReportService
{
    public function __construct(private readonly GradingScaleService $grading) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function subjectRows(Student $student, Term $term): Collection
    {
        $assessments = Assessment::query()
            ->with([
                'subject',
                'teacher',
                'results' => fn ($query) => $query->where('student_id', $student->id),
            ])
            ->where('term_id', $term->id)
            ->where('school_class_id', $student->school_class_id)
            ->get()
            ->filter(fn (Assessment $assessment) => $assessment->subject_id !== null);

        return $assessments
            ->groupBy('subject_id')
            ->map(function (Collection $subjectAssessments): array {
                /** @var Assessment $first */
                $first = $subjectAssessments->first();
                $scoreByType = collect([
                    AssessmentType::Quiz->value => 0.0,
                    AssessmentType::Test->value => 0.0,
                    AssessmentType::Project->value => 0.0,
                    AssessmentType::Exam->value => 0.0,
                ]);
                $possibleByType = $scoreByType->map(fn () => 0.0);

                foreach ($subjectAssessments as $assessment) {
                    $type = $assessment->type instanceof AssessmentType
                        ? $assessment->type->value
                        : (string) $assessment->type;
                    $result = $assessment->results->first();
                    $scoreByType[$type] = (float) ($scoreByType[$type] ?? 0) + (float) ($result?->score ?? 0);
                    $possibleByType[$type] = (float) ($possibleByType[$type] ?? 0) + max((float) $assessment->total_score, 0);
                }

                $obtained = round((float) $scoreByType->sum(), 2);
                $possible = round((float) $possibleByType->sum(), 2);
                $percentage = $possible > 0 ? round(($obtained / $possible) * 100, 2) : 0.0;
                $classification = $this->grading->classify($percentage);

                return [
                    'subject_id' => $first->subject_id,
                    'subject_name' => $first->subject?->name ?? 'Unassigned Subject',
                    'subject_code' => $first->subject?->code,
                    'quiz_score' => round((float) $scoreByType[AssessmentType::Quiz->value], 2),
                    'test_score' => round((float) $scoreByType[AssessmentType::Test->value], 2),
                    'project_score' => round((float) $scoreByType[AssessmentType::Project->value], 2),
                    'exam_score' => round((float) $scoreByType[AssessmentType::Exam->value], 2),
                    'score_obtained' => $obtained,
                    'score_possible' => $possible,
                    'percentage' => $percentage,
                    'grade' => $classification['grade'],
                    'remark' => $classification['remark'],
                    'teachers' => $subjectAssessments
                        ->pluck('teacher')
                        ->filter()
                        ->map(fn (User $teacher) => $teacher->fullName())
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy('subject_name')
            ->values();
    }

    public function compile(Student $student, Term $term): StudentTermReport
    {
        $student->loadMissing('academicSession', 'schoolClass');
        $term->loadMissing('academicSession');
        $rows = $this->subjectRows($student, $term);
        $subjectCount = $rows->count();
        $totalScore = round((float) $rows->sum('score_obtained'), 2);
        $average = $subjectCount > 0 ? round((float) $rows->avg('percentage'), 2) : 0.0;
        $classification = $this->grading->classify($average);
        $position = $subjectCount > 0
            ? $this->classPosition($student, $term, $average)
            : null;

        return StudentTermReport::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'term_id' => $term->id,
            ],
            [
                'academic_session_id' => $term->academic_session_id,
                'school_class_id' => $student->school_class_id,
                'overall_grade' => $subjectCount > 0 ? $classification['grade'] : null,
                'average_score' => $average,
                'total_score' => $totalScore,
                'subject_count' => $subjectCount,
                'class_position' => $position,
                'metadata' => [
                    'subject_rows' => $rows->all(),
                    'compiled_at' => now()->toIso8601String(),
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDetails(User $actor, StudentTermReport $report, array $data): StudentTermReport
    {
        $this->authorizeReview($actor);

        $report->update([
            'days_school_open' => $data['days_school_open'] ?? null,
            'days_present' => $data['days_present'] ?? null,
            'days_absent' => $data['days_absent'] ?? null,
            'next_term_begins_on' => $data['next_term_begins_on'] ?? null,
            'character_traits' => $data['character_traits'] ?? null,
            'practical_skills' => $data['practical_skills'] ?? null,
            'class_teacher_remark' => $data['class_teacher_remark'] ?? null,
            'guidance_remark' => $data['guidance_remark'] ?? null,
            'principal_remark' => $data['principal_remark'] ?? null,
            'house_master_remark' => $data['house_master_remark'] ?? null,
        ]);

        return $report->fresh();
    }

    public function publish(
        User $actor,
        StudentTermReport $report,
        bool $portalEnabled,
        bool $checkerEnabled,
        ?string $checkerPin = null,
    ): ReportPublicationResult {
        $this->authorizePublish($actor);

        if ((int) $report->subject_count < 1) {
            throw ValidationException::withMessages([
                'report' => 'A report cannot be published until subject scores have been compiled.',
            ]);
        }

        $plainPin = null;
        $pinHash = $report->checker_pin_hash;

        if ($checkerEnabled && filled($checkerPin)) {
            $plainPin = trim((string) $checkerPin);
            $pinHash = Hash::make($plainPin);
        } elseif ($checkerEnabled && blank($pinHash)) {
            $plainPin = (string) random_int(100000, 999999);
            $pinHash = Hash::make($plainPin);
        }

        $report->update([
            'portal_enabled' => $portalEnabled,
            'checker_enabled' => $checkerEnabled,
            'checker_pin_hash' => $checkerEnabled ? $pinHash : null,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);

        return new ReportPublicationResult($report->fresh(), $plainPin);
    }

    public function lookup(string $admissionNumber, Term $term, string $pin): StudentTermReport
    {
        $report = StudentTermReport::query()
            ->with(['student.user', 'student.schoolClass', 'term.academicSession'])
            ->where('term_id', $term->id)
            ->where('checker_enabled', true)
            ->whereNotNull('published_at')
            ->whereHas('student', fn ($query) => $query->where('admission_no', trim($admissionNumber)))
            ->first();

        if (! $report || ! $report->checker_pin_hash || ! Hash::check($pin, $report->checker_pin_hash)) {
            throw ValidationException::withMessages([
                'result' => 'The admission number, term or result PIN is incorrect.',
            ]);
        }

        return $report;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rowsForReport(StudentTermReport $report): Collection
    {
        $stored = data_get($report->metadata, 'subject_rows');

        if (is_array($stored)) {
            return collect($stored);
        }

        return $this->subjectRows($report->student, $report->term);
    }

    private function classPosition(Student $student, Term $term, float $studentAverage): int
    {
        $classmates = Student::query()
            ->where('school_class_id', $student->school_class_id)
            ->whereNull('archived_at')
            ->get();

        $averages = $classmates->mapWithKeys(function (Student $classmate) use ($term): array {
            $rows = $this->subjectRows($classmate, $term);

            return [$classmate->id => $rows->isNotEmpty() ? round((float) $rows->avg('percentage'), 2) : 0.0];
        });

        return 1 + $averages->filter(fn (float $average) => $average > $studentAverage)->count();
    }

    private function authorizeReview(User $actor): void
    {
        if (! $actor->hasAnyRole(UserRole::SuperAdmin, UserRole::Admin, UserRole::Principal)) {
            throw new AuthorizationException('You are not allowed to review student reports.');
        }
    }

    private function authorizePublish(User $actor): void
    {
        $this->authorizeReview($actor);
    }
}
