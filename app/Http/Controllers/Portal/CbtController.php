<?php

namespace App\Http\Controllers\Portal;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\CbtAttempt;
use App\Services\Cbt\CbtService;
use App\Services\Portal\PortalStudentResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CbtController extends Controller
{
    public function show(
        Request $request,
        Assessment $assessment,
        PortalStudentResolver $resolver,
        CbtService $cbt,
    ): View|RedirectResponse {
        if (! $request->user()->hasAnyRole(UserRole::Student)) {
            throw ValidationException::withMessages([
                'assessment' => 'Parents may review CBT status but cannot open or submit an examination attempt.',
            ]);
        }

        $student = $resolver->resolve($request->user());
        $attempt = $cbt->startAttempt($student, $assessment);

        if ($attempt->submitted_at || $attempt->status !== 'in_progress') {
            return redirect()->route(
                $request->routeIs('app.*') ? 'app.portal.cbt.result' : 'web.portal.cbt.result',
                $attempt,
            );
        }

        $assessment->load(['subject', 'term.academicSession', 'cbtQuestions.options']);
        $attempt->load('answers');

        return view('portal.cbt.show', [
            'student' => $student->load('user', 'schoolClass'),
            'assessment' => $assessment,
            'attempt' => $attempt,
            'savedAnswers' => $attempt->answers->keyBy('cbt_question_id'),
        ]);
    }

    public function submit(
        Request $request,
        Assessment $assessment,
        PortalStudentResolver $resolver,
        CbtService $cbt,
    ): RedirectResponse {
        abort_unless($request->user()->hasAnyRole(UserRole::Student), 403);
        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable'],
        ]);
        $attempt = $cbt->submitAttempt(
            $resolver->resolve($request->user()),
            $assessment,
            $data['answers'] ?? [],
        );

        return redirect()
            ->route($request->routeIs('app.*') ? 'app.portal.cbt.result' : 'web.portal.cbt.result', $attempt)
            ->with('status', 'CBT submitted successfully.');
    }

    public function result(Request $request, CbtAttempt $attempt, PortalStudentResolver $resolver): View
    {
        $student = $resolver->resolve($request->user(), $request->query('student_id'));
        abort_unless((int) $attempt->student_id === (int) $student->id, 403);
        $attempt->load(['assessment.subject', 'assessment.term.academicSession', 'answers.question']);
        $showScores = $attempt->assessment->cbt_show_results || $attempt->status === 'graded';

        return view('portal.cbt.result', [
            'student' => $student->load('user', 'schoolClass'),
            'attempt' => $attempt,
            'showScores' => $showScores,
        ]);
    }
}
