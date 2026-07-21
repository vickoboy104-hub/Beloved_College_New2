<?php

namespace App\Services\People;

use App\Models\SchoolClass;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StaffDirectoryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function workspace(array $filters): array
    {
        $view = in_array($filters['view'] ?? null, [
            'directory',
            'payroll',
            'class-allocation',
            'archived',
        ], true) ? $filters['view'] : 'directory';
        $query = StaffProfile::query()->with('user.managedClasses');
        $this->applyFilters($query, $filters, $view);

        $filteredProfiles = $query->orderBy('department')->orderBy('employee_no')->get();

        return [
            'view' => $view,
            'staff' => in_array($view, ['directory', 'archived'], true)
                ? $query->paginate(25)->withQueryString()
                : null,
            'payroll_rows' => $view === 'payroll' ? $this->payrollRows($filteredProfiles) : collect(),
            'class_allocation_rows' => $view === 'class-allocation' ? $this->classAllocationRows() : collect(),
            'stats' => $this->stats(),
            'departments' => StaffProfile::query()
                ->whereNotNull('department')
                ->where('department', '!=', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, string $view): void
    {
        $department = trim((string) ($filters['department'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        if ($department !== '') {
            $query->where('department', $department);
        }

        if ($search !== '') {
            foreach (array_filter(preg_split('/\s+/', mb_strtolower($search)) ?: []) as $word) {
                $like = "%{$word}%";
                $query->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->whereRaw('LOWER(employee_no) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(department) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(designation) LIKE ?', [$like])
                        ->orWhereHas('user', function (Builder $userQuery) use ($like): void {
                            $userQuery
                                ->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(role) LIKE ?', [$like]);
                        });
                });
            }
        }

        if ($view === 'archived') {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }
    }

    /**
     * @param  Collection<int, StaffProfile>  $profiles
     * @return Collection<int, array<string, mixed>>
     */
    private function payrollRows(Collection $profiles): Collection
    {
        return $profiles
            ->groupBy(fn (StaffProfile $profile) => $profile->department ?: 'General')
            ->map(function (Collection $group, string $department): array {
                $salaryProfiles = $group->filter(fn (StaffProfile $profile) => (float) $profile->salary > 0);

                return [
                    'department' => $department,
                    'staff_count' => $group->count(),
                    'staff_with_salary' => $salaryProfiles->count(),
                    'monthly_total' => (float) $group->sum(fn (StaffProfile $profile) => (float) $profile->salary),
                    'average_salary' => $salaryProfiles->isNotEmpty()
                        ? round((float) $salaryProfiles->avg('salary'), 2)
                        : 0,
                    'profiles' => $group->sortBy(fn (StaffProfile $profile) => $profile->user->fullName())->values(),
                ];
            })
            ->sortByDesc('monthly_total')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function classAllocationRows(): Collection
    {
        return SchoolClass::query()
            ->with('classTeacher.staffProfile')
            ->orderBy('name')
            ->orderBy('section')
            ->get()
            ->map(fn (SchoolClass $class) => [
                'class' => $class,
                'teacher' => $class->classTeacher,
                'department' => $class->classTeacher?->staffProfile?->department,
                'designation' => $class->classTeacher?->staffProfile?->designation,
            ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function stats(): array
    {
        $active = StaffProfile::query()->whereNull('archived_at');

        return [
            'total' => (clone $active)->count(),
            'active' => (clone $active)->where('status', 'active')->count(),
            'archived' => StaffProfile::query()->whereNotNull('archived_at')->count(),
            'salary_count' => (clone $active)->where('salary', '>', 0)->count(),
            'monthly_total' => (float) (clone $active)->sum('salary'),
            'class_teachers' => SchoolClass::query()->whereNotNull('class_teacher_id')->count(),
        ];
    }
}
