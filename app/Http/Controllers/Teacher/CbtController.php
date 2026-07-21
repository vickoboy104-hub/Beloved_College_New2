<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\AssessmentType;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\CbtAnswer;
use App\Models\CbtQuestion;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Services\Academics\TeacherAccessService;
use App\Services\Cbt\CbtService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CbtController extends Controller
{
    public function index(Request $request, TeacherAccessService $access, CbtService $cbt): View
    {
        $user = $request->user();
        $assessments = $access->scopePairs(
            Assessment::query()
                ->with([
                    'schoolClass',
                    'subject',
                    'term.academicSession',
                    'cbtQuestions.options',
                    'cbtAttempts.student.user',
                ])
                ->where('is_cbt', true),
            $user,
        )->latest()->paginate(15);

        return view('teacher.cbt.index', [
            'assessments' => $assessments,
            'classes' => SchoolClass::query()
                ->when(! $access->isPrivileged($user), fn (Builder $query) => $query->whereIn('id', $access->classIds($user) ?? []))
                ->orderBy('name')
                ->orderBy('section')
                ->get(),
            'subjects' => Subject::query()
                ->when(! $access->isPrivileged($user), fn (Builder $query) => $query->whereIn('id', $access->subjectIds($user) ?? []))
                ->orderBy('name')
                ->get(),
            'terms' => Term::query()->with('academicSession')->latest('start_date')->get(),
            'classSubjectMap' => $access->classSubjectMap($user),
            'privileged' => $access->isPrivileged($user),
            'globalEnabled' => $cbt->globalEnabled(),
        ]);
    }

    public function storeAssessment(Request $request, CbtService $cbt): RedirectResponse
    {
        $data = $request->validate([
            'term_id' => ['required', 'exists:terms,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AssessmentType::class)],
            'cbt_duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'cbt_starts_at' => ['nullable', 'date'],
            'cbt_ends_at' => ['nullable', 'date'],
            'cbt_instructions' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'cbt_is_active' => ['nullable', 'boolean'],
            'cbt_show_results' => ['nullable', 'boolean'],
        ]);

        $assessment = $cbt->createAssessment($request->user(), [
            ...$data,
            'cbt_is_active' => $request->boolean('cbt_is_active'),
            'cbt_show_results' => $request->boolean('cbt_show_results'),
        ]);
        $routeName = $request->routeIs('app.*')
            ? 'app.teacher.cbt.show'
            : 'web.teacher.cbt.show';

        return redirect()
            ->route($routeName, $assessment)
            ->with('status', 'CBT created successfully. Add questions before activation.');
    }

    public function show(Request $request, Assessment $assessment, TeacherAccessService $access): View
    {
        abort_unless($assessment->is_cbt, 404);
        $access->authorizePair($request->user(), (int) $assessment->school_class_id, (int) $assessment->subject_id);
        $assessment->load([
            'schoolClass',
            'subject',
            'term.academicSession',
            'cbtQuestions.options',
            'cbtAttempts.student.user',
            'cbtAttempts.answers.question',
        ]);

        return view('teacher.cbt.show', [
            'assessment' => $assessment,
            'pendingTheoryAnswers' => CbtAnswer::query()
                ->with(['attempt.student.user', 'question'])
                ->whereHas('question', fn ($query) => $query
                    ->where('assessment_id', $assessment->id)
                    ->where('question_type', 'theory'))
                ->whereNull('graded_at')
                ->latest()
                ->get(),
        ]);
    }

    public function addQuestion(Request $request, Assessment $assessment, CbtService $cbt): RedirectResponse
    {
        $cbt->addQuestion($request->user(), $assessment, $this->questionData($request));

        return back()->with('status', 'CBT question added successfully.');
    }

    public function updateQuestion(Request $request, CbtQuestion $question, CbtService $cbt): RedirectResponse
    {
        $cbt->updateQuestion($request->user(), $question, $this->questionData($request));

        return back()->with('status', 'CBT question updated successfully.');
    }

    public function deleteQuestion(Request $request, CbtQuestion $question, CbtService $cbt): RedirectResponse
    {
        $cbt->deleteQuestion($request->user(), $question);

        return back()->with('status', 'CBT question deleted.');
    }

    public function setAssessmentActive(Request $request, Assessment $assessment, CbtService $cbt): RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $cbt->setAssessmentActive($request->user(), $assessment, (bool) $data['active']);

        return back()->with('status', (bool) $data['active'] ? 'CBT activated.' : 'CBT deactivated.');
    }

    public function setGlobalEnabled(Request $request, CbtService $cbt): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $cbt->setGlobalEnabled($request->user(), (bool) $data['enabled']);

        return back()->with('status', (bool) $data['enabled'] ? 'Global CBT access enabled.' : 'Global CBT access disabled.');
    }

    public function gradeTheory(Request $request, CbtAnswer $answer, CbtService $cbt): RedirectResponse
    {
        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);
        $cbt->gradeTheoryAnswer($request->user(), $answer, $data);

        return back()->with('status', 'Theory answer graded successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function questionData(Request $request): array
    {
        $data = $request->validate([
            'question_type' => ['required', Rule::in(['objective', 'theory'])],
            'prompt' => ['required', 'string', 'max:30000'],
            'points' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'video_url' => ['nullable', 'url', 'max:1000'],
            'video_file' => ['nullable', 'file', 'mimes:mp4,webm,mov,m4v', 'max:51200'],
            'resource_link' => ['nullable', 'url', 'max:1000'],
            'theory_sample_answer' => ['nullable', 'string', 'max:30000'],
            'image_files' => ['nullable', 'array', 'max:10'],
            'image_files.*' => ['image', 'max:10240'],
            'options' => ['nullable', 'array', 'max:10'],
            'options.*.text' => ['nullable', 'string', 'max:5000'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'options.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            ...$data,
            'video_file' => $request->file('video_file'),
            'image_files' => $request->file('image_files', []),
            'options' => collect($data['options'] ?? [])->map(fn (array $option) => [
                ...$option,
                'is_correct' => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOL),
            ])->all(),
        ];
    }
}
