<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cms\Category;
use App\Models\Cms\CategoryTranslation;
use App\Models\User;
use App\Policies\CmsContentPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CmsCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $categories = Category::query()
            ->with(['translations', 'parent.translations'])
            ->when($request->input('parent_id'), fn ($q, $p) => $q->where('parent_id', $p))
            ->orderBy('id')
            ->paginate(50);

        return response()->json([
            'data' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'parent_id' => $c->parent_id,
                'status' => $c->status,
                'translations' => $c->translations->map(fn ($t) => [
                    'locale' => $t->locale,
                    'name' => $t->name,
                    'slug' => $t->slug,
                ]),
            ]),
            'meta' => ['page' => $categories->currentPage(), 'last' => $categories->lastPage()],
        ]);
    }

    public function show(Request $request, Category $category): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);
        $category->load(['translations', 'parent.translations']);

        return response()->json([
            'data' => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'status' => $category->status,
                'translations' => $category->translations->map(fn ($t) => [
                    'locale' => $t->locale,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'description' => $t->description,
                ]),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:cms_categories,id'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', 'in:fa,ar,en'],
            'translations.*.name' => ['required', 'string', 'max:100'],
            'translations.*.slug' => ['required', 'string', 'max:100'],
            'translations.*.description' => ['nullable', 'string', 'max:1000'],
        ]);

        $category = Category::query()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'status' => 'active',
        ]);

        foreach ($data['translations'] as $tr) {
            CategoryTranslation::query()->create([
                'category_id' => $category->id,
                'locale' => $tr['locale'],
                'name' => $tr['name'],
                'slug' => $tr['slug'],
                'description' => $tr['description'] ?? null,
            ]);
        }

        return response()->json(['data' => ['id' => $category->id]], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:cms_categories,id'],
            'translations' => ['sometimes', 'array'],
            'translations.*.locale' => ['required_with:translations', 'in:fa,ar,en'],
            'translations.*.name' => ['required_with:translations', 'string', 'max:100'],
            'translations.*.slug' => ['required_with:translations', 'string', 'max:100'],
            'translations.*.description' => ['nullable', 'string', 'max:1000'],
        ]);

        if (array_key_exists('parent_id', $data) && (int) ($data['parent_id'] ?? 0) !== (int) $category->id) {
            $category->update(['parent_id' => $data['parent_id']]);
        }

        if (isset($data['translations'])) {
            foreach ($data['translations'] as $tr) {
                CategoryTranslation::query()->updateOrCreate(
                    ['category_id' => $category->id, 'locale' => $tr['locale']],
                    [
                        'name' => $tr['name'],
                        'slug' => $tr['slug'],
                        'description' => $tr['description'] ?? null,
                    ]
                );
            }
        }

        return response()->json(['data' => ['id' => $category->id]]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        abort_unless($this->canDelete($request->user()), 403);
        $category->delete();

        return response()->json(['data' => ['id' => $category->id, 'deleted' => true]]);
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
