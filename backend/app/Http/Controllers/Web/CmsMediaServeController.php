<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cms\Media;
use Illuminate\Http\Response;

final class CmsMediaServeController extends Controller
{
    public function show(Media $media): Response
    {
        abort_unless($media->mime_type && str_starts_with($media->mime_type, 'image/'), 404);

        if (! \Storage::disk($media->disk)->exists($media->storage_key)) {
            abort(404);
        }

        $file = \Storage::disk($media->disk)->get($media->storage_key);
        $type = $media->mime_type;

        return response($file, 200, [
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
