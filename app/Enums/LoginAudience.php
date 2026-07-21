<?php

namespace App\Enums;

enum LoginAudience: string
{
    case Generic = 'generic';
    case Student = 'student';
    case Staff = 'staff';

    public function accepts(UserRole $role): bool
    {
        return match ($this) {
            self::Generic => true,
            self::Student => $role->isPortalFamily(),
            self::Staff => $role->isStaff(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Generic => 'Portal',
            self::Student => 'Student and Parent',
            self::Staff => 'Staff',
        };
    }
}
