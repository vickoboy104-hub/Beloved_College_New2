<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Academics\AcademicSetupService;
use App\Services\Academics\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicController extends Controller
{
    public function index(Request $request, PromotionService $promotions): View
    {
        $sessions = AcademicSession::query()
            ->with('closedByUser')
            ->latest('start_date')
            ->get();
        $currentSession = $sessions->firstWhere('is_current', true);
        $sourceSession = $sessions
            ->filter(fn (AcademicSession $session) => $session->closed_at !== null)
            ->sortByDesc('closed_at')
            ->first();
        $preview = $sourceSession && $currentSession && $sourceSession->id !== $currentSession->id
            ? $promotions->buildPromotionPreview($sourceSession)
            : collect();

        return view('admin.academics.index', [
            'sessions' => $sessions,
            'currentSession' => $currentSession,
            'sourceSession' => $sourceSession,
            'terms' => Term::query()->with('academicSession')->latest('start_date')->get(),
            'classes' => SchoolClass::query()->with('classTeacher')->orderBy('name')->orderBy('section')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
            'teachers' => User::query()
                ->whereIn('role', [UserRole::Teacher->value, UserRole::Principal->value])
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(),
            'promotionPreview' => $preview,
            'activeSection' => $request->string('section', 'sessions')->toString(),
        ]);
    }

    public function storeSession(Request $request, AcademicSetupService $academics): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:academic_sessions,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'promotion_pass_mark' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_current' => ['nullable', 'boolean'],
        ]);

        $academics->createSession($request->user(), [
            ...$data,
            'is_current' => $request->boolean('is_current'),
        ]);

        return back()->with('status', 'Academic session created successfully.');
    }

    public function closeSession(
        Request $request,
        AcademicSession $session,
        AcademicSetupService $academics,
    ): RedirectResponse {
        $data = $request->validate([
            'promotion_pass_mark' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $academics->closeSession(
            $request->user(),
            $session,
            (float) $data['promotion_pass_mark'],
        );

        return back()->with('status', 'Session closed successfully.');
    }

    public function storeTerm(Request $request, AcademicSetupService $academics): RedirectResponse
    {
        $data = $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_current' => ['nullable', 'boolean'],
        ]);

        $academics->createTerm($request->user(), [
            ...$data,
            'is_current' => $request->boolean('is_current'),
        ]);

        return back()->with('status', 'Academic term created successfully.');
    }

    public function storeClass(Request $request, AcademicSetupService $academics): RedirectResponse
    {
        $data = $this->classData($request);
        $academics->createClass($request->user(), $data);

        return back()->with('status', 'Class created successfully.');
    }

    public function updateClass(
        Request $request,
        SchoolClass $class,
        AcademicSetupService $academics,
    ): RedirectResponse {
        $academics->updateClass($request->user(), $class, $this->classData($request));

        return back()->with('status', 'Class details updated successfully.');
    }

    public function storeSubject(Request $request, AcademicSetupService $academics): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', 'unique:subjects,code'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $academics->createSubject($request->user(), $data);

        return back()->with('status', 'Subject created successfully.');
    }

    public function processPromotions(Request $request, PromotionService $promotions): RedirectResponse
    {
        $data = $request->validate([
            'source_session_id' => ['required', 'exists:academic_sessions,id'],
            'target_session_id' => ['required', 'exists:academic_sessions,id', 'different:source_session_id'],
            'decisions' => ['nullable', 'array'],
            'decisions.*' => ['nullable', Rule::in(['promote', 'repeat'])],
            'target_class_ids' => ['nullable', 'array'],
            'target_class_ids.*' => ['nullable', 'exists:school_classes,id'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:500'],
        ]);

        $counts = $promotions->process(
            $request->user(),
            AcademicSession::query()->findOrFail($data['source_session_id']),
            AcademicSession::query()->findOrFail($data['target_session_id']),
            $data['decisions'] ?? [],
            $data['target_class_ids'] ?? [],
            $data['notes'] ?? [],
        );

        return back()->with(
            'status',
            "Promotion processing completed. {$counts['promoted']} promoted and {$counts['repeated']} marked to repeat.",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function classData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'section' => ['nullable', 'string', 'max:255'],
            'class_teacher_id' => ['nullable', 'exists:users,id'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'room' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
