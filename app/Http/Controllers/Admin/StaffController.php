<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArchivePersonRequest;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Models\StaffProfile;
use App\Services\People\StaffDirectoryService;
use App\Services\People\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request, StaffDirectoryService $directory): View
    {
        return view('admin.staff.index', [
            ...$directory->workspace($request->only(['view', 'search', 'department'])),
            'filters' => $request->only(['view', 'search', 'department']),
        ]);
    }

    public function show(StaffProfile $staff): View
    {
        return view('admin.staff.show', [
            'staff' => $staff->load('user.managedClasses'),
        ]);
    }

    public function store(StoreStaffRequest $request, StaffService $staff): RedirectResponse
    {
        $result = $staff->create($request->user(), $request->payload());

        return redirect()
            ->route('web.admin.staff.show', $result['profile'])
            ->with('status', 'Staff account created successfully.')
            ->with('generated_credentials', [$result['credential']->jsonSerialize()]);
    }

    public function update(
        UpdateStaffRequest $request,
        StaffProfile $staff,
        StaffService $service,
    ): RedirectResponse {
        $service->update($request->user(), $staff, $request->payload());

        return back()->with('status', 'Staff record updated successfully.');
    }

    public function resetPassword(
        Request $request,
        StaffProfile $staff,
        StaffService $service,
    ): RedirectResponse {
        abort_unless($request->user()?->hasPermission('people.manage_staff'), 403);
        $credential = $service->resetTemporaryPassword($request->user(), $staff);

        return back()
            ->with('status', 'Temporary staff password generated successfully.')
            ->with('generated_credentials', [$credential->jsonSerialize()]);
    }

    public function archive(
        ArchivePersonRequest $request,
        StaffProfile $staff,
        StaffService $service,
    ): RedirectResponse {
        $service->archive($request->user(), $staff, $request->string('reason')->toString());

        return redirect()
            ->route('web.admin.staff.index', ['view' => 'archived'])
            ->with('status', 'Staff record archived.');
    }

    public function restore(
        Request $request,
        StaffProfile $staff,
        StaffService $service,
    ): RedirectResponse {
        abort_unless($request->user()?->hasPermission('people.manage_staff'), 403);
        $service->restore($request->user(), $staff);

        return redirect()
            ->route('web.admin.staff.show', $staff)
            ->with('status', 'Staff record restored.');
    }
}
