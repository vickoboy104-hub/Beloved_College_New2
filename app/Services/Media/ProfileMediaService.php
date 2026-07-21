<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileMediaService
{
    public function store(UploadedFile $file, string $identity): string
    {
        $extension = Str::lower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::slug($identity).'-'.Str::lower(Str::random(16)).'.'.$extension;

        return $file->storeAs('profile-media', $filename, 'local');
    }

    public function replace(?string $existingPath, UploadedFile $file, string $identity): string
    {
        $newPath = $this->store($file, $identity);

        if ($existingPath && str_starts_with($existingPath, 'profile-media/')) {
            Storage::disk('local')->delete($existingPath);
        }

        return $newPath;
    }
}
