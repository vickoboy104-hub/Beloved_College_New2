<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LearningMediaService
{
    public function store(UploadedFile $file, string $directory, string $identity): string
    {
        $extension = Str::lower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::slug($identity).'-'.Str::lower(Str::random(16)).'.'.$extension;

        return $file->storeAs(trim($directory, '/'), $filename, 'local');
    }

    /**
     * @param  array<int, UploadedFile>|null  $files
     * @return array<int, string>
     */
    public function storeMany(?array $files, string $directory, string $identity): array
    {
        return collect($files ?? [])
            ->filter(fn (mixed $file) => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file) => $this->store($file, $directory, $identity))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>|null  $paths
     */
    public function deleteMany(?array $paths, string $allowedPrefix): void
    {
        Collection::make($paths ?? [])
            ->filter(fn (mixed $path) => is_string($path) && str_starts_with($path, $allowedPrefix))
            ->each(fn (string $path) => Storage::disk('local')->delete($path));
    }
}
