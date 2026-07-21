<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateMediaController extends Controller
{
    public function avatar(Request $request, User $user): StreamedResponse|BinaryFileResponse
    {
        abort_unless($this->canView($request->user(), $user), 403);
        abort_unless(filled($user->avatar_path), 404);
        abort_unless(Storage::disk('local')->exists($user->avatar_path), 404);

        return Storage::disk('local')->response(
            $user->avatar_path,
            basename($user->avatar_path),
            [
                'Cache-Control' => 'private, max-age=300',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function canView(User $actor, User $subject): bool
    {
        if ($actor->is($subject)) {
            return true;
        }

        if ($subject->hasAnyRole(UserRole::Student)) {
            if ($actor->hasPermission('people.manage_students')) {
                return true;
            }

            return $actor->hasAnyRole(UserRole::Parent)
                && $subject->studentProfile?->parent_user_id === $actor->id;
        }

        return $subject->role?->isStaff()
            && $actor->hasPermission('people.manage_staff');
    }
}
