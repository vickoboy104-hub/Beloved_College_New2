<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CbtQuestion;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Academics\TeacherAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateLearningMediaController extends Controller
{
    public function lessonVideo(Request $request, Lesson $lesson): StreamedResponse|BinaryFileResponse
    {
        abort_unless($this->canViewPair($request->user(), $lesson->school_class_id, $lesson->subject_id), 403);

        return $this->respond($lesson->video_path);
    }

    public function lessonImage(Request $request, Lesson $lesson, int $index): StreamedResponse|BinaryFileResponse
    {
        abort_unless($this->canViewPair($request->user(), $lesson->school_class_id, $lesson->subject_id), 403);

        return $this->respond(($lesson->note_images ?? [])[$index] ?? null);
    }

    public function assignmentPrompt(Request $request, Assignment $assignment, int $index): StreamedResponse|BinaryFileResponse
    {
        abort_unless($this->canViewPair($request->user(), $assignment->school_class_id, $assignment->subject_id), 403);

        return $this->respond(($assignment->attachment_images ?? [])[$index] ?? null);
    }

    public function submission(Request $request, AssignmentSubmission $submission, int $index): StreamedResponse|BinaryFileResponse
    {
        $submission->loadMissing('assignment', 'student');
        $actor = $request->user();
        abort_if($actor->must_change_password, 403);
        $isOwner = $actor->studentProfile?->id === $submission->student_id;
        $isParent = $actor->hasAnyRole(UserRole::Parent)
            && $submission->student->parent_user_id === $actor->id;
        $canTeach = app(TeacherAccessService::class)->canTeach(
            $actor,
            (int) $submission->assignment->school_class_id,
            (int) $submission->assignment->subject_id,
        );

        abort_unless($isOwner || $isParent || $canTeach, 403);

        return $this->respond(($submission->attachment_paths ?? [])[$index] ?? null);
    }

    public function cbtImage(Request $request, CbtQuestion $question, int $index): StreamedResponse|BinaryFileResponse
    {
        $question->loadMissing('assessment');
        abort_unless($this->canViewPair(
            $request->user(),
            $question->assessment->school_class_id,
            $question->assessment->subject_id,
        ), 403);

        return $this->respond(($question->image_paths ?? [])[$index] ?? null);
    }

    public function cbtVideo(Request $request, CbtQuestion $question): StreamedResponse|BinaryFileResponse
    {
        $question->loadMissing('assessment');
        abort_unless($this->canViewPair(
            $request->user(),
            $question->assessment->school_class_id,
            $question->assessment->subject_id,
        ), 403);

        return $this->respond($question->video_path);
    }

    private function canViewPair(User $actor, mixed $schoolClassId, mixed $subjectId): bool
    {
        if ($actor->must_change_password) {
            return false;
        }

        if ($actor->hasAnyRole(UserRole::Student)) {
            return (int) $actor->studentProfile?->school_class_id === (int) $schoolClassId;
        }

        if ($actor->hasAnyRole(UserRole::Parent)) {
            return $actor->children()
                ->where('school_class_id', $schoolClassId)
                ->whereNull('archived_at')
                ->exists();
        }

        return app(TeacherAccessService::class)->canTeach(
            $actor,
            (int) $schoolClassId,
            (int) $subjectId,
        );
    }

    private function respond(?string $path): StreamedResponse|BinaryFileResponse
    {
        abort_unless(filled($path), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            basename($path),
            [
                'Cache-Control' => 'private, max-age=300',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
