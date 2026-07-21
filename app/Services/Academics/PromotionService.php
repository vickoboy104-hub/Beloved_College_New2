<?php

namespace App\Services\Academics;

use App\Enums\Permission;
use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPromotion;
use App\Models\User;
use App\Services\Finance\MandatoryInvoiceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function __construct(private readonly MandatoryInvoiceService $mandatoryInvoices) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function buildPromotionPreview(AcademicSession $sourceSession): Collection
    {
        $students = Student::query()
            ->with(['user', 'schoolClass'])
            ->where('academic_session_id', $sourceSession->id)
            ->orderBy('school_class_id')
            ->orderBy('admission_no')
            ->get();

        if ($students->isEmpty()) {
            return collect();
        }

        $classes = SchoolClass::query()->orderBy('name')->get()->keyBy('id');
        $classIds = $students->pluck('school_class_id')->filter()->unique()->values();

        $assessments = Assessment::query()
            ->with([
                'subject',
                'results' => fn ($query) => $query->whereIn('student_id', $students->pluck('id')),
            ])
            ->whereIn('school_class_id', $classIds)
            ->where($this->sessionAssessmentScope($sourceSession))
            ->get()
            ->groupBy('school_class_id');

        return $students->map(function (Student $student) use (
            $sourceSession,
            $assessments,
            $classes,
        ): array {
            $studentAssessments = $assessments->get($student->school_class_id, collect());
            $subjects = $studentAssessments
                ->filter(fn (Assessment $assessment) => $assessment->subject_id !== null)
                ->groupBy('subject_id');

            $subjectBreakdown = $subjects->map(function (
                Collection $subjectAssessments,
            ) use ($student): array {
                $subject = $subjectAssessments->first()?->subject;
                $possibleTotal = (float) $subjectAssessments->sum(
                    fn (Assessment $assessment) => max((float) $assessment->total_score, 0),
                );
                $studentScoreTotal = (float) $subjectAssessments->sum(
                    function (Assessment $assessment) use ($student): float {
                        $result = $assessment->results->firstWhere('student_id', $student->id);

                        return $result ? (float) $result->score : 0;
                    },
                );
                $percentage = $possibleTotal > 0
                    ? round(($studentScoreTotal / $possibleTotal) * 100, 2)
                    : 0.0;

                return [
                    'subject_id' => $subject?->id,
                    'subject_name' => $subject?->name ?? 'Unassigned',
                    'score_total' => $studentScoreTotal,
                    'possible_total' => $possibleTotal,
                    'percentage' => $percentage,
                ];
            })->values();

            $subjectCount = $subjectBreakdown->count();
            $subjectTotalPercentage = round((float) $subjectBreakdown->sum('percentage'), 2);
            $overallPercentage = $subjectCount > 0
                ? round($subjectTotalPercentage / $subjectCount, 2)
                : 0.0;
            $threshold = (float) ($sourceSession->promotion_pass_mark ?? 50);
            $recommendedStatus = $subjectCount > 0 && $overallPercentage >= $threshold
                ? 'promote'
                : 'repeat';
            $recommendedNextClass = $student->school_class_id
                ? $this->inferNextClass($classes->get($student->school_class_id), $classes)
                : null;

            return [
                'student' => $student,
                'current_class' => $student->schoolClass,
                'subject_breakdown' => $subjectBreakdown,
                'subject_count' => $subjectCount,
                'subject_total_percentage' => $subjectTotalPercentage,
                'overall_percentage' => $overallPercentage,
                'promotion_threshold' => $threshold,
                'recommended_status' => $recommendedStatus,
                'recommended_next_class' => $recommendedNextClass,
            ];
        });
    }

    /**
     * @param  array<int|string, string>  $decisions
     * @param  array<int|string, int|null>  $targetClassIds
     * @param  array<int|string, string|null>  $notes
     * @return array{promoted: int, repeated: int}
     */
    public function process(
        User $actor,
        AcademicSession $sourceSession,
        AcademicSession $targetSession,
        array $decisions = [],
        array $targetClassIds = [],
        array $notes = [],
    ): array {
        if (! $actor->hasPermission(Permission::ProcessPromotions)) {
            throw new AuthorizationException('You are not allowed to process promotions.');
        }

        if ($sourceSession->closed_at === null) {
            throw ValidationException::withMessages([
                'source_session_id' => 'The source session must be closed before promotions can be processed.',
            ]);
        }

        if (! $targetSession->is_current) {
            throw ValidationException::withMessages([
                'target_session_id' => 'Select the current active session as the destination session.',
            ]);
        }

        $preview = $this->buildPromotionPreview($sourceSession)
            ->keyBy(fn (array $row) => $row['student']->id);

        if ($preview->isEmpty()) {
            throw ValidationException::withMessages([
                'source_session_id' => 'There are no students left in the closed session to promote or repeat.',
            ]);
        }

        $pendingActions = [];
        $errors = [];

        foreach ($preview as $studentId => $row) {
            /** @var Student $student */
            $student = $row['student'];
            $decision = $decisions[$studentId] ?? $row['recommended_status'];

            if (! in_array($decision, ['promote', 'repeat'], true)) {
                $errors[] = $student->user->fullName().' has an invalid promotion decision.';

                continue;
            }

            $targetClassId = $targetClassIds[$studentId] ?? null;
            $targetClassId = $decision === 'repeat'
                ? ($targetClassId ?: $student->school_class_id)
                : ($targetClassId ?: $row['recommended_next_class']?->id);

            if (! $targetClassId) {
                $errors[] = $student->user->fullName().' needs a target class before the promotion can be processed.';

                continue;
            }

            $targetClass = SchoolClass::query()->find($targetClassId);

            if (! $targetClass) {
                $errors[] = $student->user->fullName().' has an invalid target class selection.';

                continue;
            }

            $pendingActions[] = [
                'student' => $student,
                'decision' => $decision,
                'target_class' => $targetClass,
                'row' => $row,
                'notes' => $notes[$studentId] ?? null,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'promotions' => implode(' ', $errors),
            ]);
        }

        $counts = ['promoted' => 0, 'repeated' => 0];

        DB::transaction(function () use (
            $actor,
            $pendingActions,
            $sourceSession,
            $targetSession,
            &$counts,
        ): void {
            foreach ($pendingActions as $action) {
                /** @var Student|null $student */
                $student = $action['student']->fresh(['schoolClass', 'user']);

                if (! $student
                    || (int) $student->academic_session_id !== (int) $sourceSession->id) {
                    continue;
                }

                $decision = $action['decision'];
                /** @var SchoolClass $targetClass */
                $targetClass = $action['target_class'];
                $row = $action['row'];

                $student->update([
                    'academic_session_id' => $targetSession->id,
                    'school_class_id' => $targetClass->id,
                    'status' => 'active',
                ]);

                StudentPromotion::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'from_academic_session_id' => $sourceSession->id,
                    ],
                    [
                        'to_academic_session_id' => $targetSession->id,
                        'from_school_class_id' => $row['current_class']?->id,
                        'to_school_class_id' => $targetClass->id,
                        'promotion_status' => $decision,
                        'promotion_threshold' => $row['promotion_threshold'],
                        'overall_percentage' => $row['overall_percentage'],
                        'subject_total_percentage' => $row['subject_total_percentage'],
                        'subject_count' => $row['subject_count'],
                        'approved_by' => $actor->id,
                        'approved_at' => now(),
                        'notes' => $action['notes'],
                    ],
                );

                $this->mandatoryInvoices->syncForStudent($student->fresh());
                $counts[$decision === 'promote' ? 'promoted' : 'repeated']++;
            }
        });

        return $counts;
    }

    private function sessionAssessmentScope(AcademicSession $session): \Closure
    {
        $startDate = $session->start_date?->toDateString();
        $endDate = $session->end_date?->toDateString();

        return function (Builder $query) use ($session, $startDate, $endDate): void {
            $query->where(function (Builder $nested) use (
                $session,
                $startDate,
                $endDate,
            ): void {
                $nested
                    ->whereHas(
                        'term',
                        fn (Builder $termQuery) => $termQuery
                            ->where('academic_session_id', $session->id),
                    )
                    ->orWhere(function (Builder $termLessQuery) use (
                        $startDate,
                        $endDate,
                    ): void {
                        $termLessQuery
                            ->whereNull('term_id')
                            ->whereBetween(
                                DB::raw('DATE(COALESCE(scheduled_at, created_at))'),
                                [$startDate, $endDate],
                            );
                    });
            });
        };
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     */
    private function inferNextClass(?SchoolClass $currentClass, Collection $classes): ?SchoolClass
    {
        if (! $currentClass) {
            return null;
        }

        $name = trim($currentClass->name);

        if (! preg_match('/^(.*?)(\d+)(.*)$/i', $name, $matches)) {
            return null;
        }

        $nextName = trim($matches[1].(((int) $matches[2]) + 1).$matches[3]);

        return $classes->first(
            fn (SchoolClass $class) => strcasecmp($class->name, $nextName) === 0
                && (string) $class->section === (string) $currentClass->section,
        ) ?? $classes->first(
            fn (SchoolClass $class) => strcasecmp($class->name, $nextName) === 0,
        );
    }
}
