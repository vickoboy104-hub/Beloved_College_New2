<?php

namespace App\Services\Authorization;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Auth\Access\AuthorizationException;

class PermissionService
{
    public function allows(User $user, Permission|string $permission): bool
    {
        $permissionValue = $permission instanceof Permission
            ? $permission->value
            : (string) $permission;

        if (($user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role)) === UserRole::SuperAdmin) {
            return true;
        }

        $override = $user->relationLoaded('permissionOverrides')
            ? $user->permissionOverrides->first(
                fn (UserPermissionOverride $item) => $item->permission->value === $permissionValue,
            )
            : $user->permissionOverrides()->where('permission', $permissionValue)->first();

        if ($override) {
            return $override->allowed;
        }

        $role = $user->role instanceof UserRole
            ? $user->role->value
            : (string) $user->role;

        return in_array(
            $permissionValue,
            config("permissions.role_defaults.{$role}", []),
            true,
        );
    }

    /**
     * @return array<string, bool>
     */
    public function matrix(User $user): array
    {
        $user->loadMissing('permissionOverrides');

        return collect(Permission::cases())
            ->mapWithKeys(fn (Permission $permission) => [
                $permission->value => $this->allows($user, $permission),
            ])
            ->all();
    }

    public function setOverride(
        User $actor,
        User $subject,
        Permission $permission,
        bool $allowed,
        ?string $reason = null,
    ): UserPermissionOverride {
        if (! $this->allows($actor, Permission::ManagePermissions)) {
            throw new AuthorizationException('You are not allowed to change user permissions.');
        }

        if ($subject->hasAnyRole(UserRole::SuperAdmin)
            && ! $this->allows($actor, Permission::ManageSuperAdmins)) {
            throw new AuthorizationException('Only a Super Admin may change Super Admin permissions.');
        }

        return UserPermissionOverride::query()->updateOrCreate(
            [
                'user_id' => $subject->getKey(),
                'permission' => $permission->value,
            ],
            [
                'allowed' => $allowed,
                'granted_by' => $actor->getKey(),
                'reason' => $reason,
            ],
        );
    }

    public function clearOverride(User $actor, User $subject, Permission $permission): void
    {
        if (! $this->allows($actor, Permission::ManagePermissions)) {
            throw new AuthorizationException('You are not allowed to change user permissions.');
        }

        $subject->permissionOverrides()
            ->where('permission', $permission->value)
            ->delete();
    }
}
