<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentTermReport;
use App\Models\Term;
use App\Services\Reports\StudentReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $termId = $request->integer('term_id') ?: Term::query()->where('is_current', true)->value('id');
        $classId = $request->integer('class_id') ?: null;

        return view('admin.reports.index', [
            'terms' => Term::query()->with('academicSession')->latest('start_date')->get(),
            'classes' => SchoolClass::query()->orderBy('name')->orderBy('section')->get(),
            'selectedTerm' => $termId ? Term::query()->with('academicSession')->find($termId) : null,
            'selectedClass' => $classId ? SchoolClass::query()->find($classId) : null,
            'reports' => StudentTermReport::query()
                ->with(['student.user', 'student.schoolClass', 'term.academicSession'])
                ->when($termId, fn ($query) => $query->where('term_id', $termId))
                ->when($classId, fn ($query) => $query->where('school_class_id', $classId))
                ->orderBy('class_position')
                ->orderBy('student_id')
                ->paginate(30)
                ->withQueryString(),
            'studentsWithoutReports' => $termId && $classId
                ? Student::query()
                    ->with('user')
                    ->where('school_class_id', $classId)
                    ->whereNull('archived_at')
                    ->whereDoesntHave('termReports', fn ($query) => $query->where('term_id', $termId))
                    ->orderBy('admission_no')
                    ->get()
                : collect(),
        ]);
    }

    public function compile(
        Request $request,
        Student $student,
        StudentReportService $reports,
    ): RedirectResponse {
        $data = $request->validate([
            'term_id' => ['required', 'exists:terms,id'],
        ]);

        $report = $reports->compile($student, Term::query()->findOrFail($data['term_id']));

        return redirect()
            ->route('web.admin.reports.show', $report)
            ->with('status', 'Student report compiled successfully.');
    }

    public function compileClass(Request $request, StudentReportService $reports): RedirectResponse
    {
        $data = $request->validate([
            'term_id' => ['required', 'exists:terms,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
        ]);
        $term = Term::query()->findOrFail($data['term_id']);
        $students = Student::query()
            ->where('school_class_id', $data['school_class_id'])
            ->whereNull('archived_at')
            ->get();

        DB::transaction(function () use ($students, $term, $reports): void {
            $students->each(fn (Student $student) => $reports->compile($student, $term));
        });

        return back()->with('status', $students->count().' student reports compiled.');
    }

    public function show(StudentTermReport $report, StudentReportService $reports): View
    {
        $report->load([
            'student.user',
            'student.parent',
            'student.schoolClass',
            'term.academicSession',
            'approver',
            'publisher',
        ]);

        return view('admin.reports.show', [
            'report' => $report,
            'subjectRows' => $reports->rowsForReport($report),
        ]);
    }

    public function update(
        Request $request,
        StudentTermReport $report,
        StudentReportService $reports,
    ): RedirectResponse {
        $data = $request->validate([
            'days_school_open' => ['nullable', 'integer', 'min:0', 'max:366'],
            'days_present' => ['nullable', 'integer', 'min:0', 'max:366'],
            'days_absent' => ['nullable', 'integer', 'min:0', 'max:366'],
            'next_term_begins_on' => ['nullable', 'date'],
            'character_traits' => ['nullable', 'array'],
            'character_traits.*' => ['nullable', 'string', 'max:50'],
            'practical_skills' => ['nullable', 'array'],
            'practical_skills.*' => ['nullable', 'string', 'max:50'],
            'class_teacher_remark' => ['nullable', 'string', 'max:2000'],
            'guidance_remark' => ['nullable', 'string', 'max:2000'],
            'principal_remark' => ['nullable', 'string', 'max:2000'],
            'house_master_remark' => ['nullable', 'string', 'max:2000'],
        ]);

        $reports->updateDetails($request->user(), $report, $data);

        return back()->with('status', 'Report details updated successfully.');
    }

    public function publish(
        Request $request,
        StudentTermReport $report,
        StudentReportService $reports,
    ): RedirectResponse {
        $data = $request->validate([
            'portal_enabled' => ['nullable', 'boolean'],
            'checker_enabled' => ['nullable', 'boolean'],
            'checker_pin' => ['nullable', 'string', 'min:4', 'max:30'],
        ]);

        $result = $reports->publish(
            $request->user(),
            $report,
            $request->boolean('portal_enabled'),
            $request->boolean('checker_enabled'),
            $data['checker_pin'] ?? null,
        );

        $response = back()->with('status', 'Report publication settings updated.');

        if ($result->checkerPin) {
            $response->with('generated_result_pin', [
                'student' => $report->student->user->fullName(),
                'admission_no' => $report->student->admission_no,
                'term' => $report->term->name,
                'pin' => $result->checkerPin,
            ]);
        }

        return $response;
    }

    public function print(StudentTermReport $report, StudentReportService $reports): View
    {
        $report->load([
            'student.user',
            'student.schoolClass',
            'term.academicSession',
            'approver',
            'publisher',
        ]);

        return view('reports.print', [
            'report' => $report,
            'subjectRows' => $reports->rowsForReport($report),
        ]);
    }
}
