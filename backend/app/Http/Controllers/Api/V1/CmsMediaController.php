<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cms\Media;
use App\Models\Cms\MediaTranslation;
use App\Models\User;
use App\Policies\CmsContentPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CmsMediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $media = Media::query()->with('translations')->orderByDesc('id')->paginate(24);

        return response()->json([
            'data' => $media->map(fn ($m) => $this->summary($m)),
            'meta' => ['page' => $media->currentPage(), 'last' => $media->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,svg'],
            'alt_text' => ['nullable', 'string', 'max:300'],
            'caption' => ['nullable', 'string', 'max:500'],
            'locale' => ['required', 'in:fa,ar,en'],
        ]);

        $file = $request->file('file');
        $disk = 'public-cms';
        $key = 'media/'.Str::ulid().'.'.$file->getClientOriginalExtension();
        Storage::disk($disk)->putFileAs('media', $file, basename($key));

        $dimensions = @getimagesize($file->getRealPath());

        $media = Media::query()->create([
            'uploaded_by_user_id' => $request->user()->id,
            'disk' => $disk,
            'storage_key' => $key,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'byte_size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'width' => $dimensions ? $dimensions[0] : null,
            'height' => $dimensions ? $dimensions[1] : null,
            'alt_text' => $request->input('alt_text'),
            'caption' => $request->input('caption'),
        ]);

        MediaTranslation::query()->create([
            'media_id' => $media->id,
            'locale' => $request->input('locale'),
            'alt_text' => $request->input('alt_text'),
            'caption' => $request->input('caption'),
        ]);

        return response()->json(['data' => ['id' => $media->id]], 201);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:300'],
            'caption' => ['nullable', 'string', 'max:500'],
            'translations' => ['sometimes', 'array'],
            'translations.*.locale' => ['required_with:translations', 'in:fa,ar,en'],
            'translations.*.alt_text' => ['nullable', 'string', 'max:300'],
            'translations.*.caption' => ['nullable', 'string', 'max:500'],
            'translations.*.description' => ['nullable', 'string', 'max:1000'],
        ]);

        $media->update(array_filter([
            'alt_text' => $data['alt_text'] ?? null,
            'caption' => $data['caption'] ?? null,
        ], fn ($v) => $v !== null));

        if (isset($data['translations'])) {
            foreach ($data['translations'] as $tr) {
                MediaTranslation::query()->updateOrCreate(
                    ['media_id' => $media->id, 'locale' => $tr['locale']],
                    [
                        'alt_text' => $tr['alt_text'] ?? null,
                        'caption' => $tr['caption'] ?? null,
                        'description' => $tr['description'] ?? null,
                    ]
                );
            }
        }

        return response()->json(['data' => ['id' => $media->id]]);
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        abort_unless($this->canDelete($request->user()), 403);
        Storage::disk($media->disk)->delete($media->storage_key);
        $media->delete();

        return response()->json(['data' => ['id' => $media->id, 'deleted' => true]]);
    }

    private function summary(Media $m): array
    {
        return [
            'id' => $m->id,
            'url' => Storage::disk($m->disk)->url($m->storage_key),
            'mime_type' => $m->mime_type,
            'byte_size' => $m->byte_size,
            'width' => $m->width,
            'height' => $m->height,
            'alt_text' => $m->alt_text,
            'caption' => $m->caption,
        ];
    }

    private function canManage(User $user): bool
    {
        return app(CmsContentPolicy::class)->manage($user);
    }

    private function canDelete(User $user): bool
    {
        return app(CmsContentPolicy::class)->delete($user);
    }
}
