<?php

namespace App\Services\Cbt;

use App\Enums\AssessmentType;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\CbtAnswer;
use App\Models\CbtAttempt;
use App\Models\CbtQuestion;
use App\Models\CbtQuestionOption;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\Academics\TeacherAccessService;
use App\Services\Media\LearningMediaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CbtService
{
    public function __construct(
        private readonly TeacherAccessService $teacherAccess,
        private readonly LearningMediaService $media,
    ) {}

    public function globalEnabled(): bool
    {
        return filter_var(Setting::getValue('cbt_enabled', true), FILTER_VALIDATE_BOOL);
    }

    public function setGlobalEnabled(User $actor, bool $enabled): void
    {
        $this->authorizeAdministrator($actor);
        Setting::setMany(['cbt_enabled' => $enabled ? '1' : '0'], 'cbt');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAssessment(User $teacher, array $data): Assessment
    {
        $this->teacherAccess->authorizePair(
            $teacher,
            (int) $data['school_class_id'],
            (int) $data['subject_id'],
        );

        $startsAt = isset($data['cbt_starts_at']) ? Carbon::parse($data['cbt_starts_at']) : null;
        $endsAt = isset($data['cbt_ends_at']) ? Carbon::parse($data['cbt_ends_at']) : null;

        if ($startsAt && $endsAt && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'cbt_ends_at' => 'The CBT end time must be later than the start time.',
            ]);
        }

        return Assessment::query()->create([
            'teacher_id' => $teacher->id,
            'term_id' => $data['term_id'],
            'subject_id' => $data['subject_id'],
            'school_class_id' => $data['school_class_id'],
            'title' => $data['title'],
            'type' => $data['type'] instanceof AssessmentType
                ? $data['type']
                : AssessmentType::from((string) $data['type']),
            'is_cbt' => true,
            'total_score' => 0,
            'cbt_duration_minutes' => $data['cbt_duration_minutes'],
            'scheduled_at' => $startsAt,
            'notes' => $data['notes'] ?? null,
            'cbt_starts_at' => $startsAt,
            'cbt_ends_at' => $endsAt,
            'cbt_instructions' => $data['cbt_instructions'] ?? null,
            'cbt_is_active' => (bool) ($data['cbt_is_active'] ?? false),
            'cbt_show_results' => (bool) ($data['cbt_show_results'] ?? false),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addQuestion(User $teacher, Assessment $assessment, array $data): CbtQuestion
    {
        $this->authorizeAssessment($teacher, $assessment);
        $this->assertQuestionBankOpen($assessment);
        $this->validateQuestionData($data);

        return DB::transaction(function () use ($assessment, $data): CbtQuestion {
            $identity = $assessment->id.'-'.$data['prompt'];
            $video = $data['video_file'] ?? null;
            $videoPath = $video instanceof UploadedFile
                ? $this->media->store($video, 'learning/cbt-videos', $identity)
                : null;
            $imagePaths = $this->media->storeMany(
                $data['image_files'] ?? [],
                'learning/cbt-images',
                $identity,
            );

            $question = CbtQuestion::query()->create([
                'assessment_id' => $assessment->id,
                'question_type' => $data['question_type'],
                'prompt' => $data['prompt'],
                'points' => $data['points'],
                'image_paths' => $imagePaths,
                'video_path' => $videoPath,
                'video_url' => $data['video_url'] ?? null,
                'resource_link' => $data['resource_link'] ?? null,
                'theory_sample_answer' => $data['theory_sample_answer'] ?? null,
                'sort_order' => $data['sort_order'] ?? ($assessment->cbtQuestions()->max('sort_order') + 1),
            ]);

            $this->replaceOptions($question, $data['options'] ?? []);
            $assessment->syncCbtTotalScore();

            return $question->load('options');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateQuestion(User $teacher, CbtQuestion $question, array $data): CbtQuestion
    {
        $question->loadMissing('assessment');
        $assessment = $question->assessment;
        $this->authorizeAssessment($teacher, $assessment);
        $this->assertQuestionBankOpen($assessment);
        $this->validateQuestionData($data);

        return DB::transaction(function () use ($question, $assessment, $data): CbtQuestion {
            $identity = $assessment->id.'-'.$data['prompt'];
            $video = $data['video_file'] ?? null;
            $videoPath = $question->video_path;

            if ($video instanceof UploadedFile) {
                $videoPath = $this->media->store($video, 'learning/cbt-videos', $identity);
                $this->media->deleteMany([$question->video_path], 'learning/cbt-videos/');
            }

            $newImages = $this->media->storeMany(
                $data['image_files'] ?? [],
                'learning/cbt-images',
                $identity,
            );
            $imagePaths = $newImages !== [] ? $newImages : ($question->image_paths ?? []);

            if ($newImages !== []) {
                $this->media->deleteMany($question->image_paths, 'learning/cbt-images/');
            }

            $question->update([
                'question_type' => $data['question_type'],
                'prompt' => $data['prompt'],
                'points' => $data['points'],
                'image_paths' => $imagePaths,
                'video_path' => $videoPath,
                'video_url' => $data['video_url'] ?? null,
                'resource_link' => $data['resource_link'] ?? null,
                'theory_sample_answer' => $data['theory_sample_answer'] ?? null,
                'sort_order' => $data['sort_order'] ?? $question->sort_order,
            ]);

            $this->replaceOptions($question, $data['options'] ?? []);
            $assessment->syncCbtTotalScore();

            return $question->fresh('options');
        });
    }

    public function deleteQuestion(User $teacher, CbtQuestion $question): void
    {
        $question->loadMissing('assessment');
        $assessment = $question->assessment;
        $this->authorizeAssessment($teacher, $assessment);
        $this->assertQuestionBankOpen($assessment);

        DB::transaction(function () use ($question, $assessment): void {
            $this->media->deleteMany($question->image_paths, 'learning/cbt-images/');
            $this->media->deleteMany([$question->video_path], 'learning/cbt-videos/');
            $question->delete();
            $assessment->syncCbtTotalScore();
        });
    }

    public function setAssessmentActive(User $actor, Assessment $assessment, bool $active): Assessment
    {
        $this->authorizeAdministrator($actor);

        if (! $assessment->is_cbt) {
            throw ValidationException::withMessages(['assessment' => 'The selected assessment is not a CBT.']);
        }

        if ($active && $assessment->cbtQuestions()->count() < 1) {
            throw ValidationException::withMessages(['assessment' => 'Add at least one question before activating this CBT.']);
        }

        $assessment->update(['cbt_is_active' => $active]);

        return $assessment->fresh();
    }

    public function startAttempt(Student $student, Assessment $assessment): CbtAttempt
    {
        $this->assertAssessmentOpenForStudent($student, $assessment);

        $existing = CbtAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $expiresAt = now()->addMinutes((int) $assessment->cbt_duration_minutes);

        if ($assessment->cbt_ends_at && $expiresAt->isAfter($assessment->cbt_ends_at)) {
            $expiresAt = $assessment->cbt_ends_at->copy();
        }

        return CbtAttempt::query()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'expires_at' => $expiresAt,
            'objective_score' => 0,
            'theory_score' => 0,
            'total_score' => 0,
        ]);
    }

    /**
     * @param  array<int|string, mixed>  $answers
     */
    public function submitAttempt(Student $student, Assessment $assessment, array $answers): CbtAttempt
    {
        $this->assertAssessmentOpenForStudent($student, $assessment, allowAlreadyStarted: true);
        $attempt = CbtAttempt::query()
            ->with('assessment')
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        if ($attempt->submitted_at || $attempt->status !== 'in_progress') {
            throw ValidationException::withMessages(['assessment' => 'This CBT attempt has already been submitted.']);
        }

        if (($attempt->expires_at && now()->isAfter($attempt->expires_at))
            || ($assessment->cbt_ends_at && now()->isAfter($assessment->cbt_ends_at))) {
            throw ValidationException::withMessages(['assessment' => 'The CBT submission window has closed.']);
        }

        $questions = $assessment->cbtQuestions()->with('options')->get();

        DB::transaction(function () use ($attempt, $questions, $answers): void {
            foreach ($questions as $question) {
                $value = $answers[$question->id] ?? null;

                if ($question->question_type === 'objective') {
                    $option = $question->options->firstWhere('id', (int) $value);
                    $correct = (bool) $option?->is_correct;

                    CbtAnswer::query()->updateOrCreate(
                        [
                            'cbt_attempt_id' => $attempt->id,
                            'cbt_question_id' => $question->id,
                        ],
                        [
                            'selected_option_id' => $option?->id,
                            'answer_text' => null,
                            'is_correct' => $correct,
                            'awarded_score' => $correct ? $question->points : 0,
                            'feedback' => null,
                            'graded_at' => now(),
                        ],
                    );
                } else {
                    CbtAnswer::query()->updateOrCreate(
                        [
                            'cbt_attempt_id' => $attempt->id,
                            'cbt_question_id' => $question->id,
                        ],
                        [
                            'selected_option_id' => null,
                            'answer_text' => filled($value) ? trim((string) $value) : null,
                            'is_correct' => null,
                            'awarded_score' => null,
                            'feedback' => null,
                            'graded_at' => null,
                        ],
                    );
                }
            }

            $attempt->update([
                'submitted_at' => now(),
                'status' => 'submitted',
            ]);
            $attempt->syncScores();
        });

        return $attempt->fresh(['answers.question', 'assessment']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function gradeTheoryAnswer(User $teacher, CbtAnswer $answer, array $data): CbtAnswer
    {
        $answer->loadMissing('question.assessment', 'attempt');
        $question = $answer->question;
        $assessment = $question->assessment;
        $this->authorizeAssessment($teacher, $assessment);

        if ($question->question_type !== 'theory') {
            throw ValidationException::withMessages(['answer' => 'Only theory answers require manual grading.']);
        }

        $score = (float) $data['score'];

        if ($score < 0 || $score > (float) $question->points) {
            throw ValidationException::withMessages([
                'score' => "The score must be between 0 and {$question->points}.",
            ]);
        }

        $answer->update([
            'awarded_score' => $score,
            'feedback' => $data['feedback'] ?? null,
            'graded_at' => now(),
        ]);
        $answer->attempt->update(['graded_by' => $teacher->id]);
        $answer->attempt->syncScores();

        return $answer->fresh(['question', 'attempt']);
    }

    private function authorizeAssessment(User $teacher, Assessment $assessment): void
    {
        if (! $assessment->is_cbt) {
            throw ValidationException::withMessages(['assessment' => 'The selected assessment is not a CBT.']);
        }

        $this->teacherAccess->authorizePair(
            $teacher,
            (int) $assessment->school_class_id,
            (int) $assessment->subject_id,
        );
    }

    private function authorizeAdministrator(User $actor): void
    {
        if (! $actor->hasAnyRole(UserRole::SuperAdmin, UserRole::Admin, UserRole::Principal)) {
            throw new AuthorizationException('You are not allowed to control CBT availability.');
        }
    }

    private function assertQuestionBankOpen(Assessment $assessment): void
    {
        if ($assessment->cbtAttempts()->exists()) {
            throw ValidationException::withMessages([
                'assessment' => 'The question bank is locked because a student has started this CBT.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateQuestionData(array $data): void
    {
        if (! in_array($data['question_type'], ['objective', 'theory'], true)) {
            throw ValidationException::withMessages(['question_type' => 'Question type must be objective or theory.']);
        }

        if ((float) $data['points'] <= 0) {
            throw ValidationException::withMessages(['points' => 'Question points must be greater than zero.']);
        }

        if ($data['question_type'] === 'objective') {
            $options = collect($data['options'] ?? [])->filter(fn (array $option) => filled($option['text'] ?? null));

            if ($options->count() < 2) {
                throw ValidationException::withMessages(['options' => 'Objective questions require at least two answer options.']);
            }

            if ($options->filter(fn (array $option) => (bool) ($option['is_correct'] ?? false))->count() !== 1) {
                throw ValidationException::withMessages(['options' => 'Select exactly one correct option.']);
            }
        }
    }

    /**
     * @param  array<int, array{text?: string, is_correct?: bool, sort_order?: int}>  $options
     */
    private function replaceOptions(CbtQuestion $question, array $options): void
    {
        $question->options()->delete();

        if ($question->question_type !== 'objective') {
            return;
        }

        collect($options)
            ->filter(fn (array $option) => filled($option['text'] ?? null))
            ->values()
            ->each(function (array $option, int $index) use ($question): void {
                CbtQuestionOption::query()->create([
                    'cbt_question_id' => $question->id,
                    'option_text' => trim((string) $option['text']),
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                    'sort_order' => $option['sort_order'] ?? ($index + 1),
                ]);
            });
    }

    private function assertAssessmentOpenForStudent(
        Student $student,
        Assessment $assessment,
        bool $allowAlreadyStarted = false,
    ): void {
        if (! $this->globalEnabled()) {
            throw ValidationException::withMessages(['assessment' => 'CBT access is temporarily disabled.']);
        }

        if (! $assessment->is_cbt || ! $assessment->cbt_is_active) {
            throw ValidationException::withMessages(['assessment' => 'This CBT is not active.']);
        }

        if ((int) $student->school_class_id !== (int) $assessment->school_class_id) {
            throw new AuthorizationException('This CBT does not belong to the student’s class.');
        }

        if ($assessment->cbt_starts_at && now()->isBefore($assessment->cbt_starts_at)) {
            throw ValidationException::withMessages(['assessment' => 'This CBT has not started yet.']);
        }

        if ($assessment->cbt_ends_at && now()->isAfter($assessment->cbt_ends_at)) {
            throw ValidationException::withMessages(['assessment' => 'This CBT has ended.']);
        }

        if ($assessment->cbtQuestions()->count() < 1) {
            throw ValidationException::withMessages(['assessment' => 'This CBT does not contain any questions.']);
        }

        if (! $allowAlreadyStarted) {
            return;
        }
    }
}
