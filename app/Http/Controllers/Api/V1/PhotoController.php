<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PhotoController extends Controller
{
    public function show(string $filename): BinaryFileResponse
    {
        abort_unless(preg_match('/^acreditado_[a-f0-9-]+\.(jpg|jpeg|png)$/i', $filename), 404);

        $path = Storage::disk('legacy_photos')->path($filename);
        abort_unless(is_file($path), 404);

        return response()->file($path);
    }
}
