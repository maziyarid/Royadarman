<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\CMS\Enums\MenuItemType;
use App\Http\Controllers\Controller;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Cms\MenuItemTranslation;
use App\Models\Cms\MenuTranslation;
use App\Models\User;
use App\Policies\CmsContentPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CmsMenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $menus = Menu::query()->with(['translations', 'items.translations'])->orderBy('id')->paginate(50);

        return response()->json([
            'data' => $menus->map(fn ($m) => [
                'id' => $m->id,
                'location' => $m->location,
                'slug' => $m->slug,
                'translations' => $m->translations->map(fn ($t) => [
                    'locale' => $t->locale,
                    'title' => $t->title,
                ]),
                'items' => $m->items->sortBy('sort_order')->values()->map(fn ($i) => $this->itemSummary($i)),
            ]),
            'meta' => ['page' => $menus->currentPage(), 'last' => $menus->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'location' => ['required', 'string', 'max:40'],
            'slug' => ['required', 'string', 'max:60', 'unique:cms_menus,slug'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', 'in:fa,ar,en'],
            'translations.*.title' => ['required', 'string', 'max:120'],
        ]);

        $menu = Menu::query()->create([
            'location' => $data['location'],
            'slug' => $data['slug'],
        ]);

        foreach ($data['translations'] as $tr) {
            MenuTranslation::query()->create([
                'menu_id' => $menu->id,
                'locale' => $tr['locale'],
                'title' => $tr['title'],
            ]);
        }

        return response()->json(['data' => ['id' => $menu->id]], 201);
    }

    public function update(Request $request, Menu $menu): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'location' => ['sometimes', 'string', 'max:40'],
            'translations' => ['sometimes', 'array'],
            'translations.*.locale' => ['required_with:translations', 'in:fa,ar,en'],
            'translations.*.title' => ['required_with:translations', 'string', 'max:120'],
        ]);

        if (isset($data['location'])) {
            $menu->update(['location' => $data['location']]);
        }

        if (isset($data['translations'])) {
            foreach ($data['translations'] as $tr) {
                MenuTranslation::query()->updateOrCreate(
                    ['menu_id' => $menu->id, 'locale' => $tr['locale']],
                    ['title' => $tr['title']]
                );
            }
        }

        return response()->json(['data' => ['id' => $menu->id]]);
    }

    public function destroy(Request $request, Menu $menu): JsonResponse
    {
        abort_unless($this->canDelete($request->user()), 403);
        $menu->delete();

        return response()->json(['data' => ['id' => $menu->id, 'deleted' => true]]);
    }

    public function storeItem(Request $request, Menu $menu): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:cms_menu_items,id'],
            'item_type' => ['required', 'in:custom,post,page,category,url'],
            'url' => ['nullable', 'string', 'max:500'],
            'target' => ['nullable', 'string', 'max:20', 'in:_self,_blank'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', 'in:fa,ar,en'],
            'translations.*.label' => ['required', 'string', 'max:120'],
        ]);

        $item = MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => $data['parent_id'] ?? null,
            'item_type' => $data['item_type'],
            'url' => $data['url'] ?? null,
            'target' => $data['target'] ?? '_self',
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        foreach ($data['translations'] as $tr) {
            MenuItemTranslation::query()->create([
                'menu_item_id' => $item->id,
                'locale' => $tr['locale'],
                'label' => $tr['label'],
            ]);
        }

        return response()->json(['data' => ['id' => $item->id]], 201);
    }

    public function updateItem(Request $request, Menu $menu, MenuItem $item): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);
        abort_unless((int) $item->menu_id === (int) $menu->id, 404);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:cms_menu_items,id'],
            'item_type' => ['sometimes', 'in:custom,post,page,category,url'],
            'url' => ['nullable', 'string', 'max:500'],
            'target' => ['sometimes', 'string', 'max:20', 'in:_self,_blank'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'translations' => ['sometimes', 'array'],
            'translations.*.locale' => ['required_with:translations', 'in:fa,ar,en'],
            'translations.*.label' => ['required_with:translations', 'string', 'max:120'],
        ]);

        if (array_key_exists('parent_id', $data) && (int) ($data['parent_id'] ?? 0) !== (int) $item->id) {
            $item->parent_id = $data['parent_id'];
        }
        foreach (['item_type', 'url', 'target', 'is_active', 'sort_order'] as $field) {
            if (array_key_exists($field, $data)) {
                $item->{$field} = $data[$field];
            }
        }
        $item->save();

        if (isset($data['translations'])) {
            foreach ($data['translations'] as $tr) {
                MenuItemTranslation::query()->updateOrCreate(
                    ['menu_item_id' => $item->id, 'locale' => $tr['locale']],
                    ['label' => $tr['label']]
                );
            }
        }

        return response()->json(['data' => ['id' => $item->id]]);
    }

    public function destroyItem(Request $request, Menu $menu, MenuItem $item): JsonResponse
    {
        abort_unless($this->canDelete($request->user()), 403);
        abort_unless((int) $item->menu_id === (int) $menu->id, 404);
        $item->delete();

        return response()->json(['data' => ['id' => $item->id, 'deleted' => true]]);
    }

    private function itemSummary(MenuItem $i): array
    {
        return [
            'id' => $i->id,
            'parent_id' => $i->parent_id,
            'item_type' => $i->item_type instanceof MenuItemType ? $i->item_type->value : $i->item_type,
            'url' => $i->url,
            'target' => $i->target,
            'is_active' => $i->is_active,
            'sort_order' => $i->sort_order,
            'translations' => $i->translations->map(fn ($t) => [
                'locale' => $t->locale,
                'label' => $t->label,
            ]),
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
