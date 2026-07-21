<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\AssessmentType;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\CbtAttempt;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Services\Academics\TeacherAccessService;
use App\Services\Learning\AssignmentSubmissionService;
use App\Services\Learning\AttendanceService;
use App\Services\Learning\LearningService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LearningController extends Controller
{
    public function index(Request $request, TeacherAccessService $access): View
    {
        $user = $request->user();
        $classes = SchoolClass::query()
            ->when(! $access->isPrivileged($user), fn (Builder $query) => $query->whereIn('id', $access->classIds($user) ?? []))
            ->orderBy('name')
            ->orderBy('section')
            ->get();
        $subjects = Subject::query()
            ->when(! $access->isPrivileged($user), fn (Builder $query) => $query->whereIn('id', $access->subjectIds($user) ?? []))
            ->orderBy('name')
            ->get();
        $lessons = $access->scopePairs(Lesson::query()->with(['schoolClass', 'subject']), $user)
            ->latest('published_at')
            ->limit(20)
            ->get();
        $assignments = $access->scopePairs(Assignment::query()->with(['schoolClass', 'subject']), $user)
            ->latest()
            ->limit(20)
            ->get();
        $assessments = $access->scopePairs(Assessment::query()->with(['schoolClass', 'subject', 'term']), $user)
            ->latest()
            ->limit(30)
            ->get();
        $assignmentIds = $assignments->pluck('id');
        $assessmentIds = $assessments->pluck('id');

        return view('teacher.learning.index', [
            'classes' => $classes,
            'subjects' => $subjects,
            'terms' => Term::query()->with('academicSession')->latest('start_date')->get(),
            'students' => Student::query()
                ->with(['user', 'schoolClass'])
                ->whereIn('school_class_id', $classes->pluck('id'))
                ->whereNull('archived_at')
                ->orderBy('school_class_id')
                ->orderBy('admission_no')
                ->get(),
            'lessons' => $lessons,
            'assignments' => $assignments,
            'assessments' => $assessments,
            'submissions' => AssignmentSubmission::query()
                ->with(['assignment.subject', 'student.user', 'grader'])
                ->whereIn('assignment_id', $assignmentIds)
                ->latest('submitted_at')
                ->limit(30)
                ->get(),
            'attendance' => AttendanceRecord::query()
                ->with(['student.user', 'schoolClass'])
                ->whereIn('school_class_id', $classes->pluck('id'))
                ->latest('attendance_date')
                ->limit(30)
                ->get(),
            'cbtAttempts' => CbtAttempt::query()
                ->with(['student.user', 'assessment.subject'])
                ->whereIn('assessment_id', $assessmentIds)
                ->latest('submitted_at')
                ->limit(30)
                ->get(),
            'classSubjectMap' => $access->classSubjectMap($user),
            'privileged' => $access->isPrivileged($user),
            'activeSection' => $request->string('section', 'lessons')->toString(),
        ]);
    }

    public function storeLesson(Request $request, LearningService $learning): RedirectResponse
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:50000'],
            'video_url' => ['nullable', 'url', 'max:1000'],
            'video_file' => ['nullable', 'file', 'mimes:mp4,webm,mov,m4v', 'max:51200'],
            'resource_link' => ['nullable', 'url', 'max:1000'],
            'note_images' => ['nullable', 'array', 'max:10'],
            'note_images.*' => ['image', 'max:10240'],
        ]);

        $learning->publishLesson($request->user(), [
            ...$data,
            'video_file' => $request->file('video_file'),
            'note_images' => $request->file('note_images', []),
        ]);

        return back()->with('status', 'Lesson published successfully.');
    }

    public function storeAssignment(Request $request, LearningService $learning): RedirectResponse
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'due_date' => ['nullable', 'date'],
            'total_score' => ['required', 'numeric', 'min:1', 'max:10000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'allowed_submission_types' => ['nullable', 'array'],
            'allowed_submission_types.*' => ['string', Rule::in(['text', 'file', 'image', 'pdf', 'document', 'spreadsheet', 'audio', 'video'])],
            'max_submission_files' => ['nullable', 'integer', 'min:1', 'max:10'],
            'attachment_images' => ['nullable', 'array', 'max:10'],
            'attachment_images.*' => ['image', 'max:10240'],
        ]);

        $learning->createAssignment($request->user(), [
            ...$data,
            'attachment_images' => $request->file('attachment_images', []),
        ]);

        return back()->with('status', 'Assignment created successfully.');
    }

    public function storeAssessment(Request $request, LearningService $learning): RedirectResponse
    {
        $data = $request->validate([
            'term_id' => ['required', 'exists:terms,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AssessmentType::class)],
            'total_score' => ['required', 'numeric', 'min:1', 'max:10000'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $learning->createAssessment($request->user(), $data);

        return back()->with('status', 'Assessment created successfully.');
    }

    public function recordResult(Request $request, LearningService $learning): RedirectResponse
    {
        $data = $request->validate([
            'assessment_id' => ['required', 'exists:assessments,id'],
            'student_id' => ['required', 'exists:students,id'],
            'score' => ['required', 'numeric', 'min:0'],
            'grade' => ['nullable', 'string', 'max:10'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $learning->recordResult(
            $request->user(),
            Assessment::query()->findOrFail($data['assessment_id']),
            Student::query()->findOrFail($data['student_id']),
            $data,
        );

        return back()->with('status', 'Student result recorded successfully.');
    }

    public function recordAttendance(Request $request, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'attendance_date' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.note' => ['nullable', 'string', 'max:1000'],
        ]);

        $records = $attendance->recordBulk(
            $request->user(),
            (int) $data['school_class_id'],
            $data['attendance_date'],
            $data['records'],
        );

        return back()->with('status', $records->count().' attendance records saved.');
    }

    public function gradeSubmission(
        Request $request,
        AssignmentSubmission $submission,
        AssignmentSubmissionService $submissions,
    ): RedirectResponse {
        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $submissions->grade($request->user(), $submission, $data);

        return back()->with('status', 'Assignment submission graded successfully.');
    }
}
