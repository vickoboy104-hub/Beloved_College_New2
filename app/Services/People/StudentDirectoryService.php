<?php

namespace App\Services\People;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentDirectoryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function workspace(array $filters): array
    {
        $view = in_array($filters['view'] ?? null, [
            'directory',
            'new',
            'inactive',
            'archived',
            'siblings',
            'debtors',
            'class-billing',
        ], true) ? $filters['view'] : 'directory';

        $query = Student::query()
            ->with(['user', 'parent', 'schoolClass', 'academicSession'])
            ->withSum('feeInvoices as outstanding_balance', 'balance')
            ->withSum('feeInvoices as amount_paid_total', 'amount_paid');

        $this->applyFilters($query, $filters, $view);

        return [
            'view' => $view,
            'students' => $view === 'class-billing' || $view === 'siblings'
                ? null
                : $query->orderBy('school_class_id')->orderBy('admission_no')->paginate(25)->withQueryString(),
            'sibling_rows' => $view === 'siblings' ? $this->siblingRows($filters) : collect(),
            'class_billing_rows' => $view === 'class-billing' ? $this->classBillingRows() : collect(),
            'stats' => $this->stats(),
            'classes' => SchoolClass::query()->orderBy('name')->orderBy('section')->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, string $view): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $classId = $filters['class_id'] ?? null;
        $currentSessionId = AcademicSession::query()->where('is_current', true)->value('id');

        if ($classId) {
            $query->where('school_class_id', $classId);
        }

        if ($search !== '') {
            $words = array_filter(preg_split('/\s+/', mb_strtolower($search)) ?: []);

            foreach ($words as $word) {
                $like = "%{$word}%";
                $query->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->whereRaw('LOWER(admission_no) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(student_id_no) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(guardian_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(guardian_phone) LIKE ?', [$like])
                        ->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                            $userQuery
                                ->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(phone) LIKE ?', [$like]);
                        })
                        ->orWhereHas('parent', function (Builder $parentQuery) use ($like): void {
                            $parentQuery
                                ->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(phone) LIKE ?', [$like]);
                        })
                        ->orWhereHas('schoolClass', function (Builder $classQuery) use ($like): void {
                            $classQuery
                                ->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(section) LIKE ?', [$like]);
                        });
                });
            }
        }

        match ($view) {
            'new' => $query->where(function (Builder $newQuery) use ($currentSessionId): void {
                if ($currentSessionId) {
                    $newQuery->where('academic_session_id', $currentSessionId)
                        ->orWhere('enrolled_at', '>=', now()->subDays(90)->toDateString());
                } else {
                    $newQuery->where('enrolled_at', '>=', now()->subDays(90)->toDateString());
                }
            })->whereNull('archived_at'),
            'inactive' => $query->where('status', 'inactive')->whereNull('archived_at'),
            'archived' => $query->whereNotNull('archived_at'),
            'debtors' => $query->whereHas('feeInvoices', fn (Builder $invoiceQuery) => $invoiceQuery->where('balance', '>', 0)),
            default => $query->whereNull('archived_at'),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function siblingRows(array $filters): Collection
    {
        $students = Student::query()
            ->with(['user', 'parent', 'schoolClass'])
            ->whereNotNull('parent_user_id')
            ->whereNull('archived_at')
            ->when($filters['class_id'] ?? null, fn (Builder $query, $classId) => $query->where('school_class_id', $classId))
            ->get();

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        return $students
            ->groupBy('parent_user_id')
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(function (Collection $group): array {
                /** @var Student $primary */
                $primary = $group->first();

                return [
                    'parent' => $primary->parent,
                    'students' => $group->sortBy(fn (Student $student) => $student->user->fullName())->values(),
                    'family_size' => $group->count(),
                    'class_names' => $group
                        ->map(fn (Student $student) => $student->schoolClass?->display_name ?? 'Unassigned')
                        ->unique()
                        ->values(),
                ];
            })
            ->when($search !== '', function (Collection $rows) use ($search): Collection {
                return $rows->filter(function (array $row) use ($search): bool {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $row['parent']?->fullName(),
                        $row['parent']?->email,
                        $row['students']->pluck('user.name')->implode(' '),
                        $row['students']->pluck('admission_no')->implode(' '),
                        $row['class_names']->implode(' '),
                    ])));

                    return str_contains($haystack, $search);
                });
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function classBillingRows(): Collection
    {
        $totals = DB::table('students')
            ->leftJoin('fee_invoices', 'fee_invoices.student_id', '=', 'students.id')
            ->whereNull('students.archived_at')
            ->groupBy('students.school_class_id')
            ->selectRaw('students.school_class_id')
            ->selectRaw('COUNT(DISTINCT students.id) as student_count')
            ->selectRaw('COUNT(fee_invoices.id) as invoice_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN fee_invoices.balance > 0 THEN students.id END) as students_with_debt')
            ->selectRaw('COALESCE(SUM(fee_invoices.amount_due), 0) as expected_total')
            ->selectRaw('COALESCE(SUM(fee_invoices.amount_paid), 0) as collected_total')
            ->selectRaw('COALESCE(SUM(fee_invoices.balance), 0) as outstanding_total')
            ->get()
            ->keyBy('school_class_id');

        return SchoolClass::query()
            ->orderBy('name')
            ->orderBy('section')
            ->get()
            ->map(function (SchoolClass $class) use ($totals): array {
                $row = $totals->get($class->id);
                $expected = (float) ($row->expected_total ?? 0);
                $collected = (float) ($row->collected_total ?? 0);

                return [
                    'class' => $class,
                    'student_count' => (int) ($row->student_count ?? 0),
                    'invoice_count' => (int) ($row->invoice_count ?? 0),
                    'students_with_debt' => (int) ($row->students_with_debt ?? 0),
                    'expected_total' => $expected,
                    'collected_total' => $collected,
                    'outstanding_total' => (float) ($row->outstanding_total ?? 0),
                    'collection_rate' => $expected > 0 ? round(($collected / $expected) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('outstanding_total')
            ->values();
    }

    /**
     * @return array<string, int|float>
     */
    private function stats(): array
    {
        $currentSessionId = AcademicSession::query()->where('is_current', true)->value('id');

        return [
            'total' => Student::query()->whereNull('archived_at')->count(),
            'active' => Student::query()->where('status', 'active')->whereNull('archived_at')->count(),
            'inactive' => Student::query()->where('status', 'inactive')->whereNull('archived_at')->count(),
            'archived' => Student::query()->whereNotNull('archived_at')->count(),
            'new' => Student::query()
                ->whereNull('archived_at')
                ->where(function (Builder $query) use ($currentSessionId): void {
                    if ($currentSessionId) {
                        $query->where('academic_session_id', $currentSessionId)
                            ->orWhere('enrolled_at', '>=', now()->subDays(90)->toDateString());
                    } else {
                        $query->where('enrolled_at', '>=', now()->subDays(90)->toDateString());
                    }
                })->count(),
            'debtors' => Student::query()
                ->whereNull('archived_at')
                ->whereHas('feeInvoices', fn (Builder $query) => $query->where('balance', '>', 0))
                ->count(),
            'outstanding' => (float) DB::table('fee_invoices')
                ->join('students', 'students.id', '=', 'fee_invoices.student_id')
                ->whereNull('students.archived_at')
                ->sum('fee_invoices.balance'),
        ];
    }
}
