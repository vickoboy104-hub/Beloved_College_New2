<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\ThemeMode;
use App\Enums\UserRole;
use App\Services\Authorization\PermissionService;
use App\Services\Website\ThemeService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'first_name',
    'middle_name',
    'last_name',
    'email',
    'password',
    'role',
    'phone',
    'status',
    'avatar_url',
    'avatar_path',
    'email_verified_at',
    'last_seen_at',
    'must_change_password',
    'preferred_theme',
    'archived_at',
    'archived_by',
    'archive_reason',
])]
#[Hidden(['password', 'remember_token', 'avatar_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'archived_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'must_change_password' => 'boolean',
            'preferred_theme' => ThemeMode::class,
        ];
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Student::class, 'parent_user_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'teacher_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'teacher_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'teacher_id');
    }

    public function managedClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'class_teacher_id');
    }

    public function teacherSubjectAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class, 'teacher_id');
    }

    public function gradedCbtAttempts(): HasMany
    {
        return $this->hasMany(CbtAttempt::class, 'graded_by');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'author_id');
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    public function grantedPermissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class, 'granted_by');
    }

    public function hasAnyRole(array|string|UserRole ...$roles): bool
    {
        $currentRole = $this->role instanceof UserRole
            ? $this->role->value
            : (string) $this->role;

        return collect($roles)
            ->flatten()
            ->map(fn (mixed $role) => $role instanceof UserRole ? $role->value : (string) $role)
            ->contains($currentRole);
    }

    public function hasPermission(Permission|string $permission): bool
    {
        return app(PermissionService::class)->allows($this, $permission);
    }

    public function roleLabel(): string
    {
        return $this->role instanceof UserRole
            ? $this->role->label()
            : str((string) $this->role)->headline()->toString();
    }

    public function fullName(): string
    {
        $segments = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);

        return $segments !== [] ? implode(' ', $segments) : $this->name;
    }

    public function isActive(): bool
    {
        return strtolower((string) $this->status) === 'active' && ! $this->isArchived();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isClassTeacher(): bool
    {
        return $this->managedClasses()->exists();
    }

    public function effectiveTheme(): ThemeMode
    {
        return app(ThemeService::class)->effectiveFor($this);
    }
}
