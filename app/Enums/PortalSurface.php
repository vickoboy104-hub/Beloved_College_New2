<?php

namespace App\Enums;

enum PortalSurface: string
{
    case PublicSite = 'public';
    case WebPortal = 'web';
    case AppPortal = 'app';

    public function label(): string
    {
        return match ($this) {
            self::PublicSite => 'Public Website',
            self::WebPortal => 'Full Web Portal',
            self::AppPortal => 'Mobile Portal',
        };
    }

    public function isPortal(): bool
    {
        return $this !== self::PublicSite;
    }
}
