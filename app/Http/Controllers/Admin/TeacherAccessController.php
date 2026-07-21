<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Services\Academics\TeacherAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherAccessController extends Controller
{
    public function index(): View
    {
        return view('admin.teacher-access.index', [
            'teachers' => User::query()
                ->whereIn('role', [UserRole::Teacher->value, UserRole::Principal->value])
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(),
            'classes' => SchoolClass::query()->orderBy('name')->orderBy('section')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
            'activeAssignments' => TeacherSubjectAssignment::query()
                ->with(['teacher', 'schoolClass', 'subject', 'assignedByUser'])
                ->where('is_active', true)
                ->latest('assigned_at')
                ->paginate(30, ['*'], 'active_page'),
            'revokedAssignments' => TeacherSubjectAssignment::query()
                ->with(['teacher', 'schoolClass', 'subject', 'revokedByUser'])
                ->where('is_active', false)
                ->latest('revoked_at')
                ->paginate(20, ['*'], 'revoked_page'),
        ]);
    }

    public function store(Request $request, TeacherAccessService $access): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        $access->assign(
            $request->user(),
            User::query()->findOrFail($data['teacher_id']),
            (int) $data['school_class_id'],
            (int) $data['subject_id'],
        );

        return back()->with('status', 'Teacher access assigned successfully.');
    }

    public function bulk(Request $request, TeacherAccessService $access): RedirectResponse
    {
        $data = $request->validate([
            'teacher_ids' => ['required', 'array', 'min:1'],
            'teacher_ids.*' => ['required', 'exists:users,id'],
            'school_class_ids' => ['required', 'array', 'min:1'],
            'school_class_ids.*' => ['required', 'exists:school_classes,id'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['required', 'exists:subjects,id'],
        ]);

        $records = $access->assignBulk(
            $request->user(),
            $data['teacher_ids'],
            $data['school_class_ids'],
            $data['subject_ids'],
        );

        return back()->with('status', $records->count().' teacher access combinations assigned.');
    }

    public function revoke(
        Request $request,
        TeacherSubjectAssignment $assignment,
        TeacherAccessService $access,
    ): RedirectResponse {
        $access->revoke($request->user(), $assignment);

        return back()->with('status', 'Teacher access revoked.');
    }

    public function restore(
        Request $request,
        TeacherSubjectAssignment $assignment,
        TeacherAccessService $access,
    ): RedirectResponse {
        $access->restore($request->user(), $assignment);

        return back()->with('status', 'Teacher access restored.');
    }
}
