<?php

namespace App\Services\Learning;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use App\Models\User;
use App\Services\Academics\TeacherAccessService;
use App\Services\Media\LearningMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentSubmissionService
{
    public function __construct(
        private readonly LearningMediaService $media,
        private readonly TeacherAccessService $teacherAccess,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(Student $student, Assignment $assignment, array $data): AssignmentSubmission
    {
        if ((int) $student->school_class_id !== (int) $assignment->school_class_id) {
            throw ValidationException::withMessages([
                'assignment' => 'This assignment does not belong to the student’s class.',
            ]);
        }

        if ($assignment->status !== 'published') {
            throw ValidationException::withMessages([
                'assignment' => 'This assignment is not currently accepting submissions.',
            ]);
        }

        if ($assignment->due_date && now()->isAfter($assignment->due_date)) {
            throw ValidationException::withMessages([
                'assignment' => 'The assignment deadline has passed.',
            ]);
        }

        $content = trim((string) ($data['content'] ?? ''));
        $files = collect($data['files'] ?? [])
            ->filter(fn (mixed $file) => $file instanceof UploadedFile)
            ->values();

        if ($content !== '' && ! $assignment->accepts('text')) {
            throw ValidationException::withMessages([
                'content' => 'This assignment does not accept a typed response.',
            ]);
        }

        if ($files->count() > (int) $assignment->max_submission_files) {
            throw ValidationException::withMessages([
                'files' => "A maximum of {$assignment->max_submission_files} files may be submitted.",
            ]);
        }

        foreach ($files as $file) {
            $type = $this->submissionType($file);

            if (! $assignment->accepts('file') && ! $assignment->accepts($type)) {
                throw ValidationException::withMessages([
                    'files' => "The assignment does not accept {$type} files.",
                ]);
            }
        }

        if ($content === '' && $files->isEmpty()) {
            throw ValidationException::withMessages([
                'content' => 'Enter a response or attach at least one permitted file.',
            ]);
        }

        return DB::transaction(function () use ($student, $assignment, $content, $files): AssignmentSubmission {
            $existing = AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('student_id', $student->id)
                ->first();
            $storedPaths = $this->media->storeMany(
                $files->all(),
                'learning/submissions',
                $student->admission_no.'-'.$assignment->id,
            );

            if ($existing && $storedPaths !== []) {
                $this->media->deleteMany($existing->attachment_paths, 'learning/submissions/');
            }

            return AssignmentSubmission::query()->updateOrCreate(
                [
                    'assignment_id' => $assignment->id,
                    'student_id' => $student->id,
                ],
                [
                    'content' => $content !== '' ? $content : $existing?->content,
                    'attachment_paths' => $storedPaths !== [] ? $storedPaths : ($existing?->attachment_paths ?? []),
                    'submitted_at' => now(),
                    'score' => null,
                    'feedback' => null,
                    'graded_by' => null,
                ],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function grade(User $teacher, AssignmentSubmission $submission, array $data): AssignmentSubmission
    {
        $submission->loadMissing('assignment', 'student');
        $assignment = $submission->assignment;
        $this->teacherAccess->authorizePair(
            $teacher,
            (int) $assignment->school_class_id,
            (int) $assignment->subject_id,
        );

        $score = (float) $data['score'];
        $maximum = (float) $assignment->total_score;

        if ($score < 0 || $score > $maximum) {
            throw ValidationException::withMessages([
                'score' => "The score must be between 0 and {$maximum}.",
            ]);
        }

        $submission->update([
            'score' => $score,
            'feedback' => $data['feedback'] ?? null,
            'graded_by' => $teacher->id,
        ]);

        return $submission->fresh(['assignment', 'student.user', 'grader']);
    }

    private function submissionType(UploadedFile $file): string
    {
        $mime = strtolower((string) $file->getMimeType());
        $extension = strtolower($file->getClientOriginalExtension());

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'audio/') => 'audio',
            str_starts_with($mime, 'video/') => 'video',
            $extension === 'pdf' || $mime === 'application/pdf' => 'pdf',
            in_array($extension, ['doc', 'docx', 'odt', 'txt', 'rtf'], true) => 'document',
            in_array($extension, ['xls', 'xlsx', 'csv', 'ods'], true) => 'spreadsheet',
            default => 'file',
        };
    }
}
