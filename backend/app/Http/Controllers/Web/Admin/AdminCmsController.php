<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Enums\PostType;
use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Cms\Category;
use App\Models\Cms\CategoryTranslation;
use App\Models\Cms\Comment;
use App\Models\Cms\Media;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Cms\Post;
use App\Models\Cms\PostTranslation;
use App\Models\Cms\Redirect;
use App\Models\Cms\SeoMetadata;
use App\Models\Cms\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminCmsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('viewAny', Post::class), 403);

        $posts = Post::query()
            ->with(['translations', 'author:id,name,role'])
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('q'), fn ($q, $term) => $q->whereHas('translations', fn ($qt) => $qt->where('title', 'like', '%'.$term.'%')->orWhere('slug', 'like', '%'.$term.'%')))
            ->orderByDesc('updated_at')
            ->paginate(15, ['*'], 'page', $request->integer('page', 1))
            ->withQueryString();

        return view('admin.cms.posts-index', [
            'posts' => $posts,
            'filters' => $request->only(['type', 'status', 'q']),
            'statuses' => PostStatus::cases(),
            'types' => PostType::cases(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('create', Post::class), 403);

        return view('admin.cms.post-editor', [
            'post' => new Post,
            'statuses' => PostStatus::cases(),
            'types' => PostType::cases(),
            'categories' => Category::with('translations')->get(),
            'tags' => Tag::with('translations')->get(),
            'locales' => ['fa', 'ar', 'en'],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('create', Post::class), 403);

        $data = $this->validatePost($request);

        $post = Post::query()->create([
            'author_user_id' => $request->user()->id,
            'type' => $data['type'],
            'status' => $data['status'],
            'is_featured' => $data['is_featured'] ?? false,
            'published_at' => $data['published_at'] ?? null,
        ]);

        foreach ($data['translations'] as $tr) {
            $post->translations()->create([
                'locale' => $tr['locale'],
                'title' => $tr['title'],
                'slug' => $tr['slug'],
                'excerpt' => $tr['excerpt'] ?? null,
                'body' => $tr['body'],
                'sanitized_body' => $tr['body'],
            ]);
        }

        if (! empty($data['categories'])) {
            $post->categories()->sync($data['categories']);
        }
        if (! empty($data['tags'])) {
            $post->tags()->sync($data['tags']);
        }

        return redirect()->route('admin.cms.posts.show', $post)->with('status', __('saved'));
    }

    public function show(Request $request, Post $post)
    {
        abort_unless($request->user()->can('view', $post), 403);

        $post->load(['translations', 'categories.translations', 'tags.translations', 'author:id,name,role', 'revisions.author:id,name']);

        return view('admin.cms.post-editor', [
            'post' => $post,
            'statuses' => PostStatus::cases(),
            'types' => PostType::cases(),
            'categories' => Category::with('translations')->get(),
            'tags' => Tag::with('translations')->get(),
            'locales' => ['fa', 'ar', 'en'],
        ]);
    }

    public function update(Request $request, Post $post)
    {
        abort_unless($request->user()->can('update', $post), 403);

        $data = $this->validatePost($request);

        $post->update([
            'type' => $data['type'],
            'status' => $data['status'],
            'is_featured' => $data['is_featured'] ?? false,
            'published_at' => $data['published_at'] ?? null,
        ]);

        foreach ($data['translations'] as $tr) {
            PostTranslation::query()->updateOrCreate(
                ['post_id' => $post->id, 'locale' => $tr['locale']],
                [
                    'title' => $tr['title'],
                    'slug' => $tr['slug'],
                    'excerpt' => $tr['excerpt'] ?? null,
                    'body' => $tr['body'],
                    'sanitized_body' => $tr['body'],
                ],
            );
        }

        $post->categories()->sync($data['categories'] ?? []);
        $post->tags()->sync($data['tags'] ?? []);

        return redirect()->route('admin.cms.posts.show', $post)->with('status', __('saved'));
    }

    public function publish(Request $request, Post $post)
    {
        abort_unless($request->user()->can('publish', $post), 403);
        $post->update(['status' => PostStatus::Published, 'published_at' => $post->published_at ?? now()]);

        return redirect()->route('admin.cms.posts.show', $post)->with('status', __('published'));
    }

    public function unpublish(Request $request, Post $post)
    {
        abort_unless($request->user()->can('publish', $post), 403);
        $post->update(['status' => PostStatus::Draft]);

        return redirect()->route('admin.cms.posts.show', $post)->with('status', __('unpublished'));
    }

    public function destroy(Request $request, Post $post)
    {
        abort_unless($request->user()->can('delete', $post), 403);
        $post->delete();

        return redirect()->route('admin.cms.posts.index')->with('status', __('deleted'));
    }

    private function validatePost(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::enum(PostType::class)],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', 'in:fa,ar,en'],
            'translations.*.title' => ['required', 'string', 'max:200'],
            'translations.*.slug' => ['required', 'string', 'max:200'],
            'translations.*.excerpt' => ['nullable', 'string', 'max:500'],
            'translations.*.body' => ['required', 'string'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:cms_categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:cms_tags,id'],
        ]);
    }

    private function guardManage(Request $request): void
    {
        abort_unless($request->user()->can('viewAny', Post::class), 403);
    }

    private function guardDelete(Request $request): void
    {
        abort_unless(
            in_array($request->user()->role, [UserRole::Owner, UserRole::TechnicalAdministrator], true),
            403,
        );
    }

    public function categoriesIndex(Request $request)
    {
        $this->guardManage($request);

        $categories = Category::query()
            ->with(['translations', 'parent.translations'])
            ->when($request->input('q'), fn ($q, $t) => $q->whereHas('translations', fn ($qt) => $qt->where('name', 'like', '%'.$t.'%')->orWhere('slug', 'like', '%'.$t.'%')))
            ->orderByDesc('updated_at')
            ->paginate(15, ['*'], 'page', $request->integer('page', 1))
            ->withQueryString();

        return view('admin.cms.categories-index', [
            'categories' => $categories,
            'filters' => $request->only(['q']),
        ]);
    }

    public function categoriesCreate(Request $request)
    {
        $this->guardManage($request);

        return view('admin.cms.category-editor', [
            'category' => new Category,
            'parents' => Category::with('translations')->get(),
            'locales' => ['fa', 'ar', 'en'],
        ]);
    }

    public function categoriesStore(Request $request)
    {
        $this->guardManage($request);
        $data = $this->validateCategory($request);

        $category = Category::query()->create([
            'parent_id' => $data['parent_id'] ?? null,
        ]);
        foreach ($data['translations'] as $tr) {
            $category->translations()->create($tr);
        }

        return redirect()->route('admin.cms.categories.index')->with('status', __('saved'));
    }

    public function categoriesEdit(Request $request, Category $category)
    {
        $this->guardManage($request);
        $category->load(['translations', 'parent.translations']);

        return view('admin.cms.category-editor', [
            'category' => $category,
            'parents' => Category::with('translations')->where('id', '!=', $category->id)->get(),
            'locales' => ['fa', 'ar', 'en'],
        ]);
    }

    public function categoriesUpdate(Request $request, Category $category)
    {
        $this->guardManage($request);
        $data = $this->validateCategory($request, $category);

        $category->update(['parent_id' => $data['parent_id'] ?? null]);
        foreach ($data['translations'] as $tr) {
            CategoryTranslation::query()->updateOrCreate(
                ['category_id' => $category->id, 'locale' => $tr['locale']],
                ['name' => $tr['name'], 'slug' => $tr['slug'], 'description' => $tr['description'] ?? null],
            );
        }

        return redirect()->route('admin.cms.categories.index')->with('status', __('saved'));
    }

    public function categoriesDestroy(Request $request, Category $category)
    {
        $this->guardDelete($request);
        $category->delete();

        return redirect()->route('admin.cms.categories.index')->with('status', __('deleted'));
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:cms_categories,id'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', 'in:fa,ar,en'],
            'translations.*.name' => ['required', 'string', 'max:200'],
            'translations.*.slug' => ['required', 'string', 'max:200'],
            'translations.*.description' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    public function tagsIndex(Request $request)
    {
        $this->guardManage($request);

        $tags = Tag::query()
            ->with(['translations'])
            ->when($request->input('q'), fn ($q, $t) => $q->whereHas('translations', fn ($qt) => $qt->where('name', 'like', '%'.$t.'%')))
            ->orderByDesc('updated_at')
            ->paginate(15, ['*'], 'page', $request->integer('page', 1))
            ->withQueryString();

        return view('admin.cms.tags-index', [
            'tags' => $tags,
            'filters' => $request->only(['q']),
        ]);
    }

    public function tagsStore(Request $request)
    {
        $this->guardManage($request);
        $data = $request->validate([
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', 'in:fa,ar,en'],
            'translations.*.name' => ['required', 'string', 'max:100'],
            'translations.*.slug' => ['required', 'string', 'max:100'],
        ]);

        $tag = Tag::query()->create();
        foreach ($data['translations'] as $tr) {
            $tag->translations()->create($tr);
        }

        return redirect()->route('admin.cms.tags.index')->with('status', __('saved'));
    }

    public function tagsDestroy(Request $request, Tag $tag)
    {
        $this->guardDelete($request);
        $tag->delete();

        return redirect()->route('admin.cms.tags.index')->with('status', __('deleted'));
    }

    public function mediaIndex(Request $request)
    {
        $this->guardManage($request);

        $media = Media::query()
            ->when($request->input('q'), fn ($q, $t) => $q->whereHas('translations', fn ($qt) => $qt->where('alt', 'like', '%'.$t.'%')))
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'page', $request->integer('page', 1))
            ->withQueryString();

        return view('admin.cms.media-index', [
            'media' => $media,
            'filters' => $request->only(['q']),
        ]);
    }

    public function mediaStore(Request $request)
    {
        $this->guardManage($request);
        $data = $request->validate([
            'file' => ['required', 'image', 'max:8192'],
        ]);

        $file = $request->file('file');
        $storageKey = $file->store('media', 'public-cms');
        [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

        $media = Media::query()->create([
            'uploaded_by_user_id' => $request->user()->id,
            'disk' => 'public-cms',
            'storage_key' => $storageKey,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'byte_size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'width' => $width,
            'height' => $height,
        ]);

        foreach (['fa', 'ar', 'en'] as $locale) {
            $media->translations()->create(['locale' => $locale, 'alt_text' => '', 'caption' => null, 'description' => null]);
        }

        return redirect()->route('admin.cms.media.index')->with('status', __('saved'));
    }

    public function mediaDestroy(Request $request, Media $media)
    {
        $this->guardDelete($request);
        \Storage::disk($media->disk)->delete($media->storage_key);
        $media->delete();

        return redirect()->route('admin.cms.media.index')->with('status', __('deleted'));
    }

    public function menusIndex(Request $request)
    {
        $this->guardManage($request);

        $menus = Menu::query()->with(['translations', 'items.translations'])
            ->orderByDesc('updated_at')->paginate(15, ['*'], 'page', $request->integer('page', 1))->withQueryString();

        return view('admin.cms.menus-index', ['menus' => $menus]);
    }

    public function menusStore(Request $request)
    {
        $this->guardManage($request);
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:60', 'unique:cms_menus,slug'],
            'location' => ['required', 'string', 'max:40'],
            'title_fa' => ['required', 'string', 'max:120'],
        ]);

        $menu = Menu::query()->create(['slug' => $data['slug'], 'location' => $data['location']]);
        $menu->translations()->create(['locale' => 'fa', 'title' => $data['title_fa']]);

        return redirect()->route('admin.cms.menus.index')->with('status', __('saved'));
    }

    public function menusDestroy(Request $request, Menu $menu)
    {
        $this->guardDelete($request);
        $menu->delete();

        return redirect()->route('admin.cms.menus.index')->with('status', __('deleted'));
    }

    public function menuItemsStore(Request $request, Menu $menu)
    {
        $this->guardManage($request);
        $data = $request->validate([
            'label_fa' => ['required', 'string', 'max:120'],
            'url' => ['nullable', 'string', 'max:500'],
            'parent_id' => ['nullable', 'integer', 'exists:cms_menu_items,id'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $item = $menu->items()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'url' => $data['url'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
        $item->translations()->create(['locale' => 'fa', 'label' => $data['label_fa']]);

        return redirect()->route('admin.cms.menus.index')->with('status', __('saved'));
    }

    public function menuItemsDestroy(Request $request, Menu $menu, MenuItem $item)
    {
        $this->guardDelete($request);
        $item->delete();

        return redirect()->route('admin.cms.menus.index')->with('status', __('deleted'));
    }

    public function redirectsIndex(Request $request)
    {
        $this->guardManage($request);

        $redirects = Redirect::query()->orderByDesc('updated_at')
            ->paginate(20, ['*'], 'page', $request->integer('page', 1))->withQueryString();

        return view('admin.cms.redirects-index', ['redirects' => $redirects]);
    }

    public function redirectsStore(Request $request)
    {
        $this->guardManage($request);
        $data = $request->validate([
            'source_path' => ['required', 'string', 'max:500', 'unique:cms_redirects,source_path'],
            'destination_url' => ['required', 'string', 'max:500'],
            'status_code' => ['required', 'in:301,302'],
        ]);

        Redirect::query()->create($data);

        return redirect()->route('admin.cms.redirects.index')->with('status', __('saved'));
    }

    public function redirectsDestroy(Request $request, Redirect $redirect)
    {
        $this->guardDelete($request);
        $redirect->delete();

        return redirect()->route('admin.cms.redirects.index')->with('status', __('deleted'));
    }

    public function commentsIndex(Request $request)
    {
        $this->guardManage($request);

        $comments = Comment::query()->with(['post.translations', 'author:id,name'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'page', $request->integer('page', 1))->withQueryString();

        return view('admin.cms.comments-index', [
            'comments' => $comments,
            'filters' => $request->only(['status']),
            'statuses' => ['pending', 'approved', 'spam', 'trash'],
        ]);
    }

    public function commentsApprove(Request $request, Comment $comment)
    {
        $this->guardManage($request);
        $comment->update(['status' => 'approved']);

        return redirect()->route('admin.cms.comments.index')->with('status', __('saved'));
    }

    public function commentsMarkSpam(Request $request, Comment $comment)
    {
        $this->guardManage($request);
        $comment->update(['status' => 'spam']);

        return redirect()->route('admin.cms.comments.index')->with('status', __('saved'));
    }

    public function commentsDestroy(Request $request, Comment $comment)
    {
        $this->guardDelete($request);
        $comment->delete();

        return redirect()->route('admin.cms.comments.index')->with('status', __('deleted'));
    }

    public function seoIndex(Request $request)
    {
        $this->guardManage($request);

        $seo = SeoMetadata::query()
            ->when($request->input('entity_type'), fn ($q, $t) => $q->where('entity_type', $t))
            ->orderByDesc('updated_at')
            ->paginate(20, ['*'], 'page', $request->integer('page', 1))->withQueryString();

        return view('admin.cms.seo-index', [
            'seo' => $seo,
            'filters' => $request->only(['entity_type']),
        ]);
    }

    public function seoEdit(Request $request, Post $post)
    {
        $this->guardManage($request);
        $post->load('translations');

        $seo = SeoMetadata::query()
            ->where('entity_type', 'App\\Models\\Cms\\Post')
            ->where('entity_id', $post->id)
            ->get()->keyBy('locale');

        return view('admin.cms.seo-editor', [
            'post' => $post,
            'seo' => $seo,
            'locales' => ['fa', 'ar', 'en'],
        ]);
    }

    public function seoUpdate(Request $request, Post $post)
    {
        $this->guardManage($request);
        $data = $request->validate([
            'seo' => ['required', 'array'],
            'seo.*.locale' => ['required', 'in:fa,ar,en'],
            'seo.*.seo_title' => ['nullable', 'string', 'max:200'],
            'seo.*.meta_description' => ['nullable', 'string', 'max:320'],
            'seo.*.canonical_url' => ['nullable', 'string', 'max:500'],
            'seo.*.robots_directive' => ['nullable', 'string', 'max:60'],
            'seo.*.og_title' => ['nullable', 'string', 'max:200'],
            'seo.*.og_description' => ['nullable', 'string', 'max:320'],
            'seo.*.focus_keyword' => ['nullable', 'string', 'max:120'],
        ]);

        foreach ($data['seo'] as $entry) {
            SeoMetadata::query()->updateOrCreate(
                ['entity_type' => 'App\\Models\\Cms\\Post', 'entity_id' => $post->id, 'locale' => $entry['locale']],
                [
                    'seo_title' => $entry['seo_title'] ?? null,
                    'meta_description' => $entry['meta_description'] ?? null,
                    'canonical_url' => $entry['canonical_url'] ?? null,
                    'robots_directive' => $entry['robots_directive'] ?? 'index, follow',
                    'og_title' => $entry['og_title'] ?? null,
                    'og_description' => $entry['og_description'] ?? null,
                    'focus_keyword' => $entry['focus_keyword'] ?? null,
                ],
            );
        }

        return redirect()->route('admin.cms.seo.index')->with('status', __('saved'));
    }
}
