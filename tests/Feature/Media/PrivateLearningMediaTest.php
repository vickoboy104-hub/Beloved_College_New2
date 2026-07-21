<?php

namespace Tests\Feature\Media;

use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateLearningMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_student_parent_and_assigned_teacher_can_access_learning_files(): void
    {
        [$teacher, $student, $parent, $lesson, $submission] = $this->context();

        $this->actingAs($student->user)
            ->get($this->appUrl("/private-learning-media/lessons/{$lesson->id}/images/0"))
            ->assertOk();

        $this->actingAs($parent)
            ->get($this->appUrl("/private-learning-media/submissions/{$submission->id}/files/0"))
            ->assertOk();

        $this->actingAs($teacher)
            ->get($this->webUrl("/private-learning-media/submissions/{$submission->id}/files/0"))
            ->assertOk();
    }

    public function test_unassigned_teacher_cannot_access_submission_file(): void
    {
        [$teacher, $student, $parent, $lesson, $submission] = $this->context();
        $outsider = User::factory()->role(UserRole::Teacher)->create();

        $this->actingAs($outsider)
            ->get($this->webUrl("/private-learning-media/submissions/{$submission->id}/files/0"))
            ->assertForbidden();
    }

    /**
     * @return array{User, Student, User, Lesson, AssignmentSubmission}
     */
    private function context(): array
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $parent = User::factory()->role(UserRole::Parent)->create();
        $studentUser = User::factory()->role(UserRole::Student)->create();
        $class = SchoolClass::query()->create([
            'name' => 'JSS 2',
            'slug' => 'jss-2-media-learning',
            'section' => 'A',
        ]);
        $subject = Subject::query()->create([
            'name' => 'Basic Technology',
            'code' => 'BTE-MEDIA',
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
            'parent_user_id' => $parent->id,
            'admission_no' => 'ADM-26-MEDIA-LEARNING',
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);
        $lesson = Lesson::query()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'title' => 'Workshop Safety',
            'note_images' => ['learning/lesson-images/safety.jpg'],
            'published_at' => now(),
        ]);
        $assignment = Assignment::query()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'title' => 'Safety Checklist',
            'total_score' => 10,
            'status' => 'published',
            'allowed_submission_types' => ['pdf'],
            'max_submission_files' => 1,
        ]);
        $submission = AssignmentSubmission::query()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'attachment_paths' => ['learning/submissions/checklist.pdf'],
            'submitted_at' => now(),
        ]);
        Storage::disk('local')->put('learning/lesson-images/safety.jpg', 'lesson-image');
        Storage::disk('local')->put('learning/submissions/checklist.pdf', 'submission-file');

        return [$teacher, $student, $parent, $lesson, $submission];
    }

    private function webUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.web').$path;
    }

    private function appUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.app').$path;
    }
}
