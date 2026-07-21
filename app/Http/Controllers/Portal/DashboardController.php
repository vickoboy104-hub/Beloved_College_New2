<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PortalSurface;
use App\Http\Controllers\Controller;
use App\Services\Authorization\PermissionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PermissionService $permissions): View
    {
        return view('portal.dashboard', [
            'user' => $request->user(),
            'surface' => app(PortalSurface::class),
            'permissions' => $permissions->matrix($request->user()),
        ]);
    }
}
