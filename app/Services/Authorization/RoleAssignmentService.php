<?php

namespace App\Services\Authorization;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class RoleAssignmentService
{
    public function assertCanAssign(User $actor, UserRole $role): void
    {
        $allowedRoles = match ($actor->role) {
            UserRole::SuperAdmin => UserRole::cases(),
            UserRole::Admin => [
                UserRole::Admin,
                UserRole::Principal,
                UserRole::Accountant,
                UserRole::Teacher,
            ],
            UserRole::Principal => [
                UserRole::Accountant,
                UserRole::Teacher,
            ],
            default => [],
        };

        if (! in_array($role, $allowedRoles, true)) {
            throw new AuthorizationException(
                "Your account cannot assign the {$role->label()} role.",
            );
        }
    }

    public function assertCanManage(User $actor, User $subject): void
    {
        if ($subject->hasAnyRole(UserRole::SuperAdmin)
            && ! $actor->hasAnyRole(UserRole::SuperAdmin)) {
            throw new AuthorizationException('Only a Super Admin may manage a Super Admin account.');
        }

        if ($subject->hasAnyRole(UserRole::Admin)
            && ! $actor->hasAnyRole(UserRole::SuperAdmin, UserRole::Admin)) {
            throw new AuthorizationException('A Principal cannot manage an Admin account.');
        }
    }
}
