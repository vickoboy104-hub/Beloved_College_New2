<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArchivePersonRequest;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Services\People\StudentDirectoryService;
use App\Services\People\StudentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request, StudentDirectoryService $directory): View
    {
        return view('admin.students.index', [
            ...$directory->workspace($request->only(['view', 'search', 'class_id'])),
            'filters' => $request->only(['view', 'search', 'class_id']),
        ]);
    }

    public function show(Student $student): View
    {
        return view('admin.students.show', [
            'student' => $student->load([
                'user',
                'parent',
                'schoolClass',
                'academicSession',
                'feeInvoices.feeItem',
                'termReports.term.academicSession',
                'promotions.fromAcademicSession',
                'promotions.toAcademicSession',
            ]),
            'classes' => SchoolClass::query()->orderBy('name')->orderBy('section')->get(),
            'terms' => Term::query()->with('academicSession')->latest('start_date')->get(),
        ]);
    }

    public function store(StoreStudentRequest $request, StudentService $students): RedirectResponse
    {
        $result = $students->create($request->payload());

        return redirect()
            ->route('web.admin.students.show', $result->student)
            ->with('status', 'Student registered successfully.')
            ->with('generated_credentials', collect($result->credentials)
                ->map(fn ($credential) => $credential->jsonSerialize())
                ->all());
    }

    public function update(
        UpdateStudentRequest $request,
        Student $student,
        StudentService $students,
    ): RedirectResponse {
        $students->update($student, $request->payload());

        return back()->with('status', 'Student record updated successfully.');
    }

    public function resetPassword(
        Student $student,
        StudentService $students,
    ): RedirectResponse {
        $credential = $students->resetTemporaryPassword($student);

        return back()
            ->with('status', 'Temporary student password generated successfully.')
            ->with('generated_credentials', [$credential->jsonSerialize()]);
    }

    public function archive(
        ArchivePersonRequest $request,
        Student $student,
        StudentService $students,
    ): RedirectResponse {
        $students->archive($student, $request->user(), $request->string('reason')->toString());

        return redirect()
            ->route('web.admin.students.index', ['view' => 'archived'])
            ->with('status', 'Student record archived.');
    }

    public function restore(
        Request $request,
        Student $student,
        StudentService $students,
    ): RedirectResponse {
        abort_unless($request->user()?->hasPermission('people.manage_students'), 403);
        $students->restore($student);

        return redirect()
            ->route('web.admin.students.show', $student)
            ->with('status', 'Student record restored.');
    }
}
