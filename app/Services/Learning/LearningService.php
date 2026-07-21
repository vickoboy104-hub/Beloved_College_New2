<?php

namespace App\Services\Learning;

use App\Enums\AssessmentType;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\User;
use App\Services\Academics\TeacherAccessService;
use App\Services\Media\LearningMediaService;
use App\Services\Reports\GradingScaleService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearningService
{
    public function __construct(
        private readonly TeacherAccessService $teacherAccess,
        private readonly LearningMediaService $media,
        private readonly GradingScaleService $grading,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function publishLesson(User $teacher, array $data): Lesson
    {
        $this->teacherAccess->authorizePair(
            $teacher,
            (int) $data['school_class_id'],
            (int) $data['subject_id'],
        );

        return DB::transaction(function () use ($teacher, $data): Lesson {
            $identity = $data['title'].'-'.$teacher->id;
            $video = $data['video_file'] ?? null;
            $videoPath = $video instanceof UploadedFile
                ? $this->media->store($video, 'learning/lesson-videos', $identity)
                : null;
            $noteImages = $this->media->storeMany(
                $data['note_images'] ?? [],
                'learning/lesson-images',
                $identity,
            );

            return Lesson::query()->create([
                'teacher_id' => $teacher->id,
                'subject_id' => $data['subject_id'],
                'school_class_id' => $data['school_class_id'],
                'title' => $data['title'],
                'summary' => $data['summary'] ?? null,
                'body' => $data['body'] ?? null,
                'video_url' => $data['video_url'] ?? null,
                'video_path' => $videoPath,
                'resource_link' => $data['resource_link'] ?? null,
                'note_images' => $noteImages,
                'published_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAssignment(User $teacher, array $data): Assignment
    {
        $this->teacherAccess->authorizePair(
            $teacher,
            (int) $data['school_class_id'],
            (int) $data['subject_id'],
        );

        return DB::transaction(function () use ($teacher, $data): Assignment {
            $images = $this->media->storeMany(
                $data['attachment_images'] ?? [],
                'learning/assignment-prompts',
                $data['title'].'-'.$teacher->id,
            );
            $submissionTypes = collect($data['allowed_submission_types'] ?? ['text'])
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($submissionTypes === []) {
                $submissionTypes = ['text'];
            }

            return Assignment::query()->create([
                'teacher_id' => $teacher->id,
                'subject_id' => $data['subject_id'],
                'school_class_id' => $data['school_class_id'],
                'title' => $data['title'],
                'instructions' => $data['instructions'] ?? null,
                'attachment_images' => $images,
                'due_date' => $data['due_date'] ?? null,
                'total_score' => $data['total_score'] ?? 100,
                'status' => $data['status'] ?? 'published',
                'allowed_submission_types' => $submissionTypes,
                'max_submission_files' => $data['max_submission_files'] ?? 3,
            ]);
        });
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

        return Assessment::query()->create([
            'teacher_id' => $teacher->id,
            'term_id' => $data['term_id'],
            'subject_id' => $data['subject_id'],
            'school_class_id' => $data['school_class_id'],
            'title' => $data['title'],
            'type' => $data['type'] instanceof AssessmentType
                ? $data['type']
                : AssessmentType::from((string) $data['type']),
            'is_cbt' => false,
            'total_score' => $data['total_score'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordResult(User $teacher, Assessment $assessment, Student $student, array $data): AssessmentResult
    {
        $assessment->loadMissing('schoolClass');
        $this->teacherAccess->authorizePair(
            $teacher,
            (int) $assessment->school_class_id,
            (int) $assessment->subject_id,
        );

        if ((int) $student->school_class_id !== (int) $assessment->school_class_id) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected student does not belong to the assessment class.',
            ]);
        }

        $score = (float) $data['score'];
        $maximum = (float) $assessment->total_score;

        if ($score < 0 || $score > $maximum) {
            throw ValidationException::withMessages([
                'score' => "The score must be between 0 and {$maximum}.",
            ]);
        }

        $percentage = $maximum > 0 ? ($score / $maximum) * 100 : 0;
        $classification = $this->grading->classify($percentage);

        return AssessmentResult::query()->updateOrCreate(
            [
                'assessment_id' => $assessment->id,
                'student_id' => $student->id,
            ],
            [
                'score' => $score,
                'grade' => $data['grade'] ?? $classification['grade'],
                'remark' => $data['remark'] ?? $classification['remark'],
            ],
        );
    }
}
