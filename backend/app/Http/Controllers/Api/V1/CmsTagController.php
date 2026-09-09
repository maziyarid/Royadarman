<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cms\Tag;
use App\Models\Cms\TagTranslation;
use App\Models\User;
use App\Policies\CmsContentPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class CmsTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $tags = Tag::query()->with('translations')->orderBy('id')->paginate(50);

        return response()->json([
            'data' => $tags->map(fn ($t) => [
                'id' => $t->id,
                'translations' => $t->translations->map(fn ($tr) => [
                    'locale' => $tr->locale,
                    'name' => $tr->name,
                    'slug' => $tr->slug,
                ]),
            ]),
            'meta' => ['page' => $tags->currentPage(), 'last' => $tags->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', 'in:fa,ar,en'],
            'translations.*.name' => ['required', 'string', 'max:100'],
            'translations.*.slug' => ['required', 'string', 'max:100'],
        ]);

        $tag = Tag::query()->create();
        foreach ($data['translations'] as $tr) {
            TagTranslation::query()->create([
                'tag_id' => $tag->id,
                'locale' => $tr['locale'],
                'name' => $tr['name'],
                'slug' => $tr['slug'],
            ]);
        }

        return response()->json(['data' => ['id' => $tag->id]], 201);
    }

    public function merge(Request $request, Tag $tag): JsonResponse
    {
        abort_unless($this->canDelete($request->user()), 403);
        $data = $request->validate([
            'target_tag_id' => ['required', 'exists:cms_tags,id', Rule::notIn([$tag->id])],
        ]);

        $target = Tag::query()->findOrFail($data['target_tag_id']);
        DB::table('cms_post_tag')->where('tag_id', $tag->id)->update(['tag_id' => $target->id]);
        $tag->delete();

        return response()->json(['data' => ['merged_into' => $target->id]]);
    }

    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        abort_unless($this->canDelete($request->user()), 403);
        $tag->delete();

        return response()->json(['data' => ['id' => $tag->id, 'deleted' => true]]);
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
