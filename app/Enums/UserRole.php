<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Principal = 'principal';
    case Teacher = 'teacher';
    case Accountant = 'accountant';
    case Parent = 'parent';
    case Student = 'student';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    public function isStaff(): bool
    {
        return in_array($this, [
            self::SuperAdmin,
            self::Admin,
            self::Principal,
            self::Teacher,
            self::Accountant,
        ], true);
    }

    public function isPortalFamily(): bool
    {
        return in_array($this, [self::Parent, self::Student], true);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
