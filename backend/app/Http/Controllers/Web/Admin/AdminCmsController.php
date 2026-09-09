<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Enums\PostType;
use App\Http\Controllers\Controller;
use App\Models\Cms\Category;
use App\Models\Cms\Post;
use App\Models\Cms\PostTranslation;
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
}
