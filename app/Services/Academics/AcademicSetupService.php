<?php

namespace App\Services\Academics;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcademicSetupService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createSession(User $actor, array $data): AcademicSession
    {
        $this->authorize($actor, Permission::ManageSessions);

        return DB::transaction(function () use ($data): AcademicSession {
            if ((bool) ($data['is_current'] ?? false)) {
                AcademicSession::query()->update(['is_current' => false]);
            }

            return AcademicSession::query()->create([
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'promotion_pass_mark' => $data['promotion_pass_mark'] ?? 50,
                'is_current' => (bool) ($data['is_current'] ?? false),
            ]);
        });
    }

    public function closeSession(
        User $actor,
        AcademicSession $session,
        float $promotionPassMark,
    ): AcademicSession {
        $this->authorize($actor, Permission::ManageSessions);

        if ($session->closed_at !== null) {
            throw ValidationException::withMessages([
                'session' => 'This academic session has already been closed.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $session,
            $promotionPassMark,
        ): AcademicSession {
            $session->update([
                'promotion_pass_mark' => $promotionPassMark,
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'is_current' => false,
            ]);

            Term::query()
                ->where('academic_session_id', $session->id)
                ->update(['is_current' => false]);

            return $session->fresh(['terms', 'closedByUser']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTerm(User $actor, array $data): Term
    {
        $this->authorize($actor, Permission::ManageAcademicStructure);

        return DB::transaction(function () use ($data): Term {
            if ((bool) ($data['is_current'] ?? false)) {
                Term::query()->update(['is_current' => false]);
            }

            return Term::query()->create([
                'academic_session_id' => $data['academic_session_id'],
                'name' => $data['name'],
                'slug' => Str::slug($data['name'].'-'.Str::random(4)),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_current' => (bool) ($data['is_current'] ?? false),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createClass(User $actor, array $data): SchoolClass
    {
        $this->authorize($actor, Permission::ManageAcademicStructure);
        $this->assertClassTeacher($data['class_teacher_id'] ?? null);

        return SchoolClass::query()->create([
            ...$data,
            'slug' => Str::slug(
                $data['name'].'-'.($data['section'] ?: Str::random(4)),
            ).'-'.Str::lower(Str::random(4)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateClass(User $actor, SchoolClass $class, array $data): SchoolClass
    {
        $this->authorize($actor, Permission::ManageAcademicStructure);
        $this->assertClassTeacher($data['class_teacher_id'] ?? null);
        $class->update($data);

        return $class->fresh(['classTeacher']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSubject(User $actor, array $data): Subject
    {
        $this->authorize($actor, Permission::ManageAcademicStructure);

        return Subject::query()->create($data);
    }

    private function assertClassTeacher(mixed $teacherId): void
    {
        if (! $teacherId) {
            return;
        }

        $teacher = User::query()->find($teacherId);

        if (! $teacher?->hasAnyRole(UserRole::Teacher, UserRole::Principal)) {
            throw ValidationException::withMessages([
                'class_teacher_id' => 'The selected class teacher must be a Teacher or Principal.',
            ]);
        }

        if (SchoolClass::query()->where('class_teacher_id', $teacher->id)->exists()) {
            throw ValidationException::withMessages([
                'class_teacher_id' => 'This teacher is already assigned as the class teacher of another class.',
            ]);
        }
    }

    private function authorize(User $actor, Permission $permission): void
    {
        if (! $actor->hasPermission($permission)) {
            throw new AuthorizationException('You are not allowed to manage this academic setting.');
        }
    }
}
