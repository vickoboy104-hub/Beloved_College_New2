<?php

namespace App\Services\People;

use App\Data\GeneratedCredential;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Identity\CredentialGenerator;
use Illuminate\Validation\ValidationException;

class ParentAccountService
{
    public function __construct(private readonly CredentialGenerator $credentials) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{parent: ?User, credential: ?GeneratedCredential}
     */
    public function sync(array $data, ?User $existingParent = null): array
    {
        $email = filled($data['parent_email'] ?? null)
            ? mb_strtolower(trim((string) $data['parent_email']))
            : null;
        $name = filled($data['parent_name'] ?? null)
            ? trim((string) $data['parent_name'])
            : null;
        $phone = filled($data['parent_phone'] ?? null)
            ? trim((string) $data['parent_phone'])
            : null;

        if (! $email && ! $name && ! $phone) {
            return ['parent' => $existingParent, 'credential' => null];
        }

        if (! $email) {
            if ($existingParent) {
                $existingParent->update([
                    'name' => $name ?: $existingParent->name,
                    'phone' => $phone ?: $existingParent->phone,
                ]);
            }

            return ['parent' => $existingParent, 'credential' => null];
        }

        $parent = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($parent && ! $parent->hasAnyRole(UserRole::Parent)) {
            throw ValidationException::withMessages([
                'parent_email' => 'This email belongs to a non-parent account and cannot be reassigned automatically.',
            ]);
        }

        if ($parent) {
            $parent->update([
                'name' => $name ?: $parent->name,
                'phone' => $phone ?: $parent->phone,
                'status' => 'active',
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
            ]);

            return ['parent' => $parent, 'credential' => null];
        }

        $temporaryPassword = $this->credentials->temporaryPassword();
        $parentName = $name ?: 'Parent Account';
        $parent = User::query()->create([
            'name' => $parentName,
            'email' => $email,
            'phone' => $phone,
            'role' => UserRole::Parent,
            'status' => 'active',
            'password' => $temporaryPassword,
            'email_verified_at' => now(),
            'must_change_password' => true,
        ]);

        return [
            'parent' => $parent,
            'credential' => new GeneratedCredential(
                audience: 'parent',
                name: $parentName,
                identifier: $email,
                email: $email,
                temporaryPassword: $temporaryPassword,
            ),
        ];
    }
}
