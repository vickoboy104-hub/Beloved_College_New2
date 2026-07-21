<?php

namespace App\Services\Academics;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherAccessService
{
    /** @var array<int, Collection<int, TeacherSubjectAssignment>> */
    private array $assignmentCache = [];

    public function isPrivileged(User $user): bool
    {
        return $user->hasAnyRole(
            UserRole::SuperAdmin,
            UserRole::Admin,
            UserRole::Principal,
        );
    }

    /**
     * @return Collection<int, TeacherSubjectAssignment>
     */
    public function activeAssignments(User $user): Collection
    {
        if ($this->isPrivileged($user)) {
            return collect();
        }

        return $this->assignmentCache[$user->id] ??= TeacherSubjectAssignment::query()
            ->with(['schoolClass', 'subject'])
            ->where('teacher_id', $user->id)
            ->where('is_active', true)
            ->orderBy('school_class_id')
            ->orderBy('subject_id')
            ->get();
    }

    public function refresh(User $user): void
    {
        unset($this->assignmentCache[$user->id]);
    }

    /**
     * @return Collection<int, int>|null
     */
    public function classIds(User $user): ?Collection
    {
        if ($this->isPrivileged($user)) {
            return null;
        }

        return $this->activeAssignments($user)
            ->pluck('school_class_id')
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>|null
     */
    public function subjectIds(User $user): ?Collection
    {
        if ($this->isPrivileged($user)) {
            return null;
        }

        return $this->activeAssignments($user)
            ->pluck('subject_id')
            ->unique()
            ->values();
    }

    public function canTeach(User $user, int $schoolClassId, int $subjectId): bool
    {
        if ($this->isPrivileged($user)) {
            return true;
        }

        return $this->activeAssignments($user)->contains(
            fn (TeacherSubjectAssignment $assignment): bool =>
                (int) $assignment->school_class_id === $schoolClassId
                && (int) $assignment->subject_id === $subjectId,
        );
    }

    public function canManageClass(User $user, int $schoolClassId): bool
    {
        if ($this->isPrivileged($user)) {
            return true;
        }

        return $this->activeAssignments($user)->contains(
            fn (TeacherSubjectAssignment $assignment): bool =>
                (int) $assignment->school_class_id === $schoolClassId,
        );
    }

    public function authorizePair(User $user, int $schoolClassId, int $subjectId): void
    {
        abort_unless(
            $this->canTeach($user, $schoolClassId, $subjectId),
            403,
            'You are not assigned to teach this subject in the selected class.',
        );
    }

    public function authorizeClass(User $user, int $schoolClassId): void
    {
        abort_unless(
            $this->canManageClass($user, $schoolClassId),
            403,
            'You do not have teaching access to this class.',
        );
    }

    public function scopePairs(
        Builder $query,
        User $user,
        string $classColumn = 'school_class_id',
        string $subjectColumn = 'subject_id',
    ): Builder {
        if ($this->isPrivileged($user)) {
            return $query;
        }

        $assignments = $this->activeAssignments($user);

        if ($assignments->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $pairQuery) use (
            $assignments,
            $classColumn,
            $subjectColumn,
        ): void {
            foreach ($assignments as $assignment) {
                $pairQuery->orWhere(function (Builder $assignmentQuery) use (
                    $assignment,
                    $classColumn,
                    $subjectColumn,
                ): void {
                    $assignmentQuery
                        ->where($classColumn, $assignment->school_class_id)
                        ->where($subjectColumn, $assignment->subject_id);
                });
            }
        });
    }

    /**
     * @return array<int|string, array<int, int>>
     */
    public function classSubjectMap(User $user): array
    {
        if ($this->isPrivileged($user)) {
            return [];
        }

        return $this->activeAssignments($user)
            ->groupBy('school_class_id')
            ->map(fn (Collection $assignments) => $assignments
                ->pluck('subject_id')
                ->unique()
                ->values()
                ->all())
            ->all();
    }

    public function assign(
        User $actor,
        User $teacher,
        int $schoolClassId,
        int $subjectId,
    ): TeacherSubjectAssignment {
        $this->authorizeAdministration($actor);
        $this->assertTeacher($teacher);

        $assignment = TeacherSubjectAssignment::query()->updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'school_class_id' => $schoolClassId,
                'subject_id' => $subjectId,
            ],
            [
                'assigned_by' => $actor->id,
                'revoked_by' => null,
                'is_active' => true,
                'assigned_at' => now(),
                'revoked_at' => null,
            ],
        );

        $this->refresh($teacher);

        return $assignment->load(['teacher', 'schoolClass', 'subject']);
    }

    /**
     * @param  array<int, int>  $teacherIds
     * @param  array<int, int>  $classIds
     * @param  array<int, int>  $subjectIds
     * @return Collection<int, TeacherSubjectAssignment>
     */
    public function assignBulk(
        User $actor,
        array $teacherIds,
        array $classIds,
        array $subjectIds,
    ): Collection {
        $this->authorizeAdministration($actor);
        $teachers = User::query()->whereIn('id', $teacherIds)->get();

        foreach ($teachers as $teacher) {
            $this->assertTeacher($teacher);
        }

        return DB::transaction(function () use (
            $actor,
            $teachers,
            $classIds,
            $subjectIds,
        ): Collection {
            $records = collect();

            foreach ($teachers as $teacher) {
                foreach (array_unique($classIds) as $classId) {
                    foreach (array_unique($subjectIds) as $subjectId) {
                        $records->push($this->assign(
                            $actor,
                            $teacher,
                            (int) $classId,
                            (int) $subjectId,
                        ));
                    }
                }
            }

            return $records;
        });
    }

    public function revoke(User $actor, TeacherSubjectAssignment $assignment): TeacherSubjectAssignment
    {
        $this->authorizeAdministration($actor);
        $assignment->update([
            'is_active' => false,
            'revoked_by' => $actor->id,
            'revoked_at' => now(),
        ]);
        $this->refresh($assignment->teacher);

        return $assignment->fresh(['teacher', 'schoolClass', 'subject']);
    }

    public function restore(User $actor, TeacherSubjectAssignment $assignment): TeacherSubjectAssignment
    {
        $this->authorizeAdministration($actor);
        $assignment->update([
            'is_active' => true,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
            'revoked_by' => null,
            'revoked_at' => null,
        ]);
        $this->refresh($assignment->teacher);

        return $assignment->fresh(['teacher', 'schoolClass', 'subject']);
    }

    private function authorizeAdministration(User $actor): void
    {
        if (! $actor->hasPermission(Permission::ManageTeacherAccess)) {
            throw new AuthorizationException('You are not allowed to manage teacher access.');
        }
    }

    private function assertTeacher(User $teacher): void
    {
        if (! $teacher->hasAnyRole(UserRole::Teacher, UserRole::Principal)) {
            throw ValidationException::withMessages([
                'teacher_id' => 'The selected account is not a Teacher or Principal.',
            ]);
        }
    }
}
