<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Services\Authorization\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $permissionEnum = Permission::tryFrom($permission);

        abort_unless($permissionEnum, 500, 'The configured permission is invalid.');
        abort_unless(
            $request->user() && $this->permissions->allows($request->user(), $permissionEnum),
            403,
        );

        return $next($request);
    }
}
