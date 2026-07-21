<?php

namespace App\Services\Identity;

use App\Enums\LoginAudience;
use App\Enums\UserRole;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;

class LoginIdentifierResolver
{
    public function resolve(string $identifier, LoginAudience $audience): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $normalized = mb_strtolower($identifier);

        $user = User::query()
            ->where(function ($query) use ($normalized): void {
                $query
                    ->whereRaw('LOWER(email) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized]);
            })
            ->first();

        if (! $user && $audience !== LoginAudience::Staff) {
            $student = Student::query()
                ->where('admission_no', $identifier)
                ->orWhere('student_id_no', $identifier)
                ->with('user')
                ->first();

            $user = $student?->user;
        }

        if (! $user && $audience !== LoginAudience::Student) {
            $profile = StaffProfile::query()
                ->where('employee_no', $identifier)
                ->with('user')
                ->first();

            $user = $profile?->user;
        }

        if (! $user || ! $user->isActive()) {
            return null;
        }

        $role = $user->role instanceof UserRole
            ? $user->role
            : UserRole::tryFrom((string) $user->role);

        if (! $role || ! $audience->accepts($role)) {
            return null;
        }

        return $user;
    }
}
