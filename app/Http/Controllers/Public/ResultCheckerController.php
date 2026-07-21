<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\StudentTermReport;
use App\Models\Term;
use App\Services\Reports\StudentReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResultCheckerController extends Controller
{
    public function index(): View
    {
        return view('public.result-checker.index', [
            'terms' => Term::query()
                ->with('academicSession')
                ->whereHas('studentReports', fn ($query) => $query
                    ->where('checker_enabled', true)
                    ->whereNotNull('published_at'))
                ->latest('start_date')
                ->get(),
        ]);
    }

    public function lookup(Request $request, StudentReportService $reports): View
    {
        $data = $request->validate([
            'admission_no' => ['required', 'string', 'max:255'],
            'term_id' => ['required', 'exists:terms,id'],
            'pin' => ['required', 'string', 'max:30'],
        ]);
        $report = $reports->lookup(
            $data['admission_no'],
            Term::query()->findOrFail($data['term_id']),
            $data['pin'],
        );

        return view('public.result-checker.show', [
            'report' => $report,
            'subjectRows' => $reports->rowsForReport($report),
        ]);
    }
}
