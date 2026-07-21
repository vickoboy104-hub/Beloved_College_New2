<?php

namespace App\Services\Portal;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class PortalStudentResolver
{
    public function resolve(User $user, mixed $requestedStudentId = null): Student
    {
        if ($user->hasAnyRole(UserRole::Student)) {
            $student = $user->studentProfile;

            if (! $student) {
                throw new AuthorizationException('No student profile is linked to this account.');
            }

            return $student;
        }

        if ($user->hasAnyRole(UserRole::Parent)) {
            $query = $user->children()
                ->whereNull('archived_at')
                ->orderBy('admission_no');

            if ($requestedStudentId) {
                $query->whereKey((int) $requestedStudentId);
            }

            $student = $query->first();

            if (! $student) {
                throw new AuthorizationException('The selected student is not linked to this parent account.');
            }

            return $student;
        }

        throw new AuthorizationException('This account does not have a student portal context.');
    }
}
