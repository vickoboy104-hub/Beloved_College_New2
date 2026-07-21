<?php

namespace App\Services\People;

use App\Data\GeneratedCredential;
use App\Enums\UserRole;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentService;
use App\Services\Identity\CredentialGenerator;
use App\Services\Media\ProfileMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StaffService
{
    private const PROFILE_FIELDS = [
        'department',
        'designation',
        'qualification',
        'hire_date',
        'salary',
    ];

    public function __construct(
        private readonly CredentialGenerator $credentials,
        private readonly ProfileMediaService $profileMedia,
        private readonly RoleAssignmentService $roles,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{profile: StaffProfile, credential: GeneratedCredential}
     */
    public function create(User $actor, array $data): array
    {
        return DB::transaction(function () use ($actor, $data): array {
            $role = $data['role'] instanceof UserRole
                ? $data['role']
                : UserRole::from((string) $data['role']);
            $this->roles->assertCanAssign($actor, $role);

            $temporaryPassword = filled($data['password'] ?? null)
                ? (string) $data['password']
                : $this->credentials->temporaryPassword();
            $employeeNumber = filled($data['employee_no'] ?? null)
                ? trim((string) $data['employee_no'])
                : $this->credentials->employeeNumber();
            $name = $this->fullName($data);
            $passport = $data['passport_photo'] ?? null;
            $avatarPath = $passport instanceof UploadedFile
                ? $this->profileMedia->store($passport, 'staff-'.$employeeNumber)
                : null;

            $user = User::query()->create([
                'name' => $name,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'email' => mb_strtolower(trim((string) $data['email'])),
                'phone' => $data['phone'] ?? null,
                'role' => $role,
                'status' => 'active',
                'password' => $temporaryPassword,
                'email_verified_at' => now(),
                'must_change_password' => true,
                'avatar_path' => $avatarPath,
            ]);

            $profile = StaffProfile::query()->create([
                ...Arr::only($data, self::PROFILE_FIELDS),
                'user_id' => $user->id,
                'employee_no' => $employeeNumber,
                'status' => 'active',
            ]);

            return [
                'profile' => $profile->load('user'),
                'credential' => new GeneratedCredential(
                    audience: 'staff',
                    name: $name,
                    identifier: $employeeNumber,
                    email: $user->email,
                    temporaryPassword: $temporaryPassword,
                ),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, StaffProfile $profile, array $data): StaffProfile
    {
        return DB::transaction(function () use ($actor, $profile, $data): StaffProfile {
            $profile->loadMissing('user');
            $this->roles->assertCanManage($actor, $profile->user);

            $role = $data['role'] instanceof UserRole
                ? $data['role']
                : UserRole::from((string) $data['role']);
            $this->roles->assertCanAssign($actor, $role);

            $passport = $data['passport_photo'] ?? null;
            $avatarPath = $profile->user->avatar_path;

            if ($passport instanceof UploadedFile) {
                $avatarPath = $this->profileMedia->replace(
                    $avatarPath,
                    $passport,
                    'staff-'.$profile->employee_no,
                );
            }

            $profile->user->update([
                'name' => $this->fullName($data),
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'email' => mb_strtolower(trim((string) $data['email'])),
                'phone' => $data['phone'] ?? null,
                'role' => $role,
                'status' => $data['status'] ?? $profile->user->status,
                'avatar_path' => $avatarPath,
            ]);

            $profile->update([
                ...Arr::only($data, self::PROFILE_FIELDS),
                'employee_no' => $data['employee_no'] ?? $profile->employee_no,
                'status' => $data['status'] ?? $profile->status,
            ]);

            return $profile->fresh(['user']);
        });
    }

    public function resetTemporaryPassword(User $actor, StaffProfile $profile): GeneratedCredential
    {
        $profile->loadMissing('user');
        $this->roles->assertCanManage($actor, $profile->user);
        $temporaryPassword = $this->credentials->temporaryPassword();
        $profile->user->update([
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ]);

        return new GeneratedCredential(
            audience: 'staff',
            name: $profile->user->fullName(),
            identifier: $profile->employee_no,
            email: $profile->user->email,
            temporaryPassword: $temporaryPassword,
        );
    }

    public function archive(User $actor, StaffProfile $profile, string $reason): StaffProfile
    {
        return DB::transaction(function () use ($actor, $profile, $reason): StaffProfile {
            $profile->loadMissing('user');
            $this->roles->assertCanManage($actor, $profile->user);
            $values = [
                'status' => 'inactive',
                'archived_at' => now(),
                'archived_by' => $actor->id,
                'archive_reason' => $reason,
            ];

            $profile->forceFill($values)->save();
            $profile->user->forceFill($values)->save();

            return $profile->fresh(['user']);
        });
    }

    public function restore(User $actor, StaffProfile $profile): StaffProfile
    {
        return DB::transaction(function () use ($actor, $profile): StaffProfile {
            $profile->loadMissing('user');
            $this->roles->assertCanManage($actor, $profile->user);
            $values = [
                'status' => 'active',
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
            ];

            $profile->forceFill($values)->save();
            $profile->user->forceFill($values)->save();

            return $profile->fresh(['user']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fullName(array $data): string
    {
        return collect([
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
        ])->filter()->implode(' ');
    }
}
