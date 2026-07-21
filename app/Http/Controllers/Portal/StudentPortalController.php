<?php

namespace App\Http\Controllers\Portal;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Lesson;
use App\Models\StudentTermReport;
use App\Services\Learning\AssignmentSubmissionService;
use App\Services\Portal\PortalStudentResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentPortalController extends Controller
{
    public function index(Request $request, PortalStudentResolver $resolver): View
    {
        $student = $resolver->resolve($request->user(), $request->query('student_id'));
        $student->loadMissing(['user', 'parent', 'schoolClass', 'academicSession']);

        return view('portal.student.index', [
            'student' => $student,
            'children' => $request->user()->hasAnyRole(UserRole::Parent)
                ? $request->user()->children()->with(['user', 'schoolClass'])->whereNull('archived_at')->get()
                : collect([$student]),
            'lessons' => Lesson::query()
                ->with(['teacher', 'subject'])
                ->where('school_class_id', $student->school_class_id)
                ->whereNotNull('published_at')
                ->latest('published_at')
                ->get(),
            'assignments' => Assignment::query()
                ->with([
                    'teacher',
                    'subject',
                    'submissions' => fn ($query) => $query->where('student_id', $student->id),
                ])
                ->where('school_class_id', $student->school_class_id)
                ->where('status', 'published')
                ->latest()
                ->get(),
            'assessmentResults' => $student->assessmentResults()
                ->with(['assessment.subject', 'assessment.term'])
                ->latest()
                ->get(),
            'reports' => StudentTermReport::query()
                ->with(['term.academicSession', 'publisher'])
                ->where('student_id', $student->id)
                ->where('portal_enabled', true)
                ->whereNotNull('published_at')
                ->latest('published_at')
                ->get(),
            'attendance' => AttendanceRecord::query()
                ->with(['schoolClass', 'takenBy'])
                ->where('student_id', $student->id)
                ->latest('attendance_date')
                ->get(),
            'cbtAssessments' => Assessment::query()
                ->with(['subject', 'term', 'cbtAttempts' => fn ($query) => $query->where('student_id', $student->id)])
                ->where('school_class_id', $student->school_class_id)
                ->where('is_cbt', true)
                ->where('cbt_is_active', true)
                ->where(function (Builder $query): void {
                    $query->whereNull('cbt_ends_at')->orWhere('cbt_ends_at', '>=', now());
                })
                ->orderBy('cbt_starts_at')
                ->get(),
            'invoices' => $student->feeInvoices()->with(['feeItem', 'payments'])->latest('issued_at')->get(),
            'activeSection' => $request->string('section', 'overview')->toString(),
        ]);
    }

    public function submitAssignment(
        Request $request,
        Assignment $assignment,
        PortalStudentResolver $resolver,
        AssignmentSubmissionService $submissions,
    ): RedirectResponse {
        if (! $request->user()->hasAnyRole(UserRole::Student)) {
            throw ValidationException::withMessages([
                'assignment' => 'Parents may review assignments but cannot submit work for a student.',
            ]);
        }

        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:50000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:51200', 'mimes:pdf,doc,docx,odt,txt,rtf,xls,xlsx,csv,ods,jpg,jpeg,png,webp,gif,mp3,wav,m4a,ogg,mp4,webm,mov,m4v'],
        ]);

        $submissions->submit(
            $resolver->resolve($request->user()),
            $assignment,
            [
                ...$data,
                'files' => $request->file('files', []),
            ],
        );

        return back()->with('status', 'Assignment submitted successfully.');
    }
}
