<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LegacyPhotoStorageService
{
    public function store(UploadedFile $photo): string
    {
        $filename = 'acreditado_'.Str::uuid().'.'.$photo->extension();
        Storage::disk('legacy_photos')->putFileAs('', $photo, $filename);

        return 'images/fotos/'.$filename;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('legacy_photos')->delete(basename($path));
    }
}
