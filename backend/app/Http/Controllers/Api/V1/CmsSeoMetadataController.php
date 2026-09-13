<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cms\SeoMetadata;
use App\Models\User;
use App\Policies\CmsContentPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CmsSeoMetadataController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'entity_type' => ['nullable', 'string', 'max:100'],
            'entity_id' => ['nullable', 'integer'],
        ]);

        $seo = SeoMetadata::query()
            ->when($data['entity_type'] ?? null, fn ($q, $t) => $q->where('entity_type', $t))
            ->when($data['entity_id'] ?? null, fn ($q, $id) => $q->where('entity_id', $id))
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => $seo->map(fn ($s) => $this->summary($s)),
            'meta' => ['page' => $seo->currentPage(), 'last' => $seo->lastPage()],
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'entity_type' => ['required', 'string', 'max:100'],
            'entity_id' => ['required', 'integer'],
            'locale' => ['required', 'in:fa,ar,en'],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'canonical_url' => ['nullable', 'string', 'max:500'],
            'robots_directive' => ['nullable', 'string', 'max:60'],
            'og_title' => ['nullable', 'string', 'max:200'],
            'og_description' => ['nullable', 'string', 'max:320'],
            'og_image_media_id' => ['nullable', 'exists:cms_media,id'],
            'twitter_card' => ['nullable', 'string', 'max:20', 'in:summary,summary_large_image'],
            'schema_type' => ['nullable', 'string', 'max:40'],
            'schema_data' => ['nullable', 'array'],
            'focus_keyword' => ['nullable', 'string', 'max:120'],
        ]);

        $seo = SeoMetadata::query()->updateOrCreate(
            ['entity_type' => $data['entity_type'], 'entity_id' => $data['entity_id'], 'locale' => $data['locale']],
            [
                'seo_title' => $data['seo_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'canonical_url' => $data['canonical_url'] ?? null,
                'robots_directive' => $data['robots_directive'] ?? 'index, follow',
                'og_title' => $data['og_title'] ?? null,
                'og_description' => $data['og_description'] ?? null,
                'og_image_media_id' => $data['og_image_media_id'] ?? null,
                'twitter_card' => $data['twitter_card'] ?? 'summary_large_image',
                'schema_type' => $data['schema_type'] ?? null,
                'schema_data' => $data['schema_data'] ?? null,
                'focus_keyword' => $data['focus_keyword'] ?? null,
            ]
        );

        return response()->json(['data' => ['id' => $seo->id]]);
    }

    public function destroy(Request $request, SeoMetadata $seoMetadata): JsonResponse
    {
        abort_unless($this->canDelete($request->user()), 403);
        $seoMetadata->delete();

        return response()->json(['data' => ['id' => $seoMetadata->id, 'deleted' => true]]);
    }

    private function summary(SeoMetadata $s): array
    {
        return [
            'id' => $s->id,
            'entity_type' => $s->entity_type,
            'entity_id' => $s->entity_id,
            'locale' => $s->locale,
            'seo_title' => $s->seo_title,
            'meta_description' => $s->meta_description,
            'canonical_url' => $s->canonical_url,
            'robots_directive' => $s->robots_directive,
            'twitter_card' => $s->twitter_card,
            'schema_type' => $s->schema_type,
            'focus_keyword' => $s->focus_keyword,
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
