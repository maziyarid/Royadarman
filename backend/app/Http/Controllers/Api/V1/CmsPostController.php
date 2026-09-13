<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\CMS\Enums\PostStatus;
use App\Domain\CMS\Enums\PostType;
use App\Domain\CMS\Services\HtmlSanitizer;
use App\Http\Controllers\Controller;
use App\Models\Cms\Post;
use App\Models\Cms\PostRevision;
use App\Models\Cms\PostTranslation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CmsPostController extends Controller
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('viewAny', Post::class), 403);

        $posts = Post::query()
            ->with(['translations', 'author:id,role'])
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json([
            'data' => $posts->map(fn ($p) => $this->summary($p)),
            'meta' => ['page' => $posts->currentPage(), 'last' => $posts->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('create', Post::class), 403);

        $data = $request->validate([
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
        ]);

        $post = Post::query()->create([
            'author_user_id' => $request->user()->id,
            'type' => $data['type'],
            'status' => $data['status'],
            'is_featured' => $data['is_featured'] ?? false,
            'published_at' => $data['published_at'] ?? null,
        ]);

        foreach ($data['translations'] as $tr) {
            PostTranslation::query()->create([
                'post_id' => $post->id,
                'locale' => $tr['locale'],
                'title' => $tr['title'],
                'slug' => $tr['slug'],
                'excerpt' => $tr['excerpt'] ?? null,
                'body' => $tr['body'],
                'sanitized_body' => $this->sanitizer->sanitize($tr['body']),
            ]);
            $this->recordRevision($post, $request->user(), $tr);
        }

        return response()->json(['data' => ['id' => $post->id]], 201);
    }

    public function show(Request $request, Post $post): JsonResponse
    {
        abort_unless($request->user()->can('view', $post), 403);

        return response()->json(['data' => $this->detail($post)]);
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        abort_unless($request->user()->can('update', $post), 403);

        $data = $request->validate([
            'status' => ['nullable', Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'translations' => ['sometimes', 'array'],
            'translations.*.locale' => ['required_with:translations', 'in:fa,ar,en'],
            'translations.*.title' => ['required_with:translations', 'string', 'max:200'],
            'translations.*.slug' => ['required_with:translations', 'string', 'max:200'],
            'translations.*.excerpt' => ['nullable', 'string', 'max:500'],
            'translations.*.body' => ['required_with:translations', 'string'],
        ]);

        $post->update(array_filter([
            'status' => $data['status'] ?? null,
            'is_featured' => $data['is_featured'] ?? null,
            'published_at' => array_key_exists('published_at', $data) ? $data['published_at'] : null,
        ], fn ($v) => $v !== null));

        if (isset($data['translations'])) {
            foreach ($data['translations'] as $tr) {
                PostTranslation::query()->updateOrCreate(
                    ['post_id' => $post->id, 'locale' => $tr['locale']],
                    [
                        'title' => $tr['title'],
                        'slug' => $tr['slug'],
                        'excerpt' => $tr['excerpt'] ?? null,
                        'body' => $tr['body'],
                        'sanitized_body' => $this->sanitizer->sanitize($tr['body']),
                    ]
                );
                $this->recordRevision($post, $request->user(), $tr);
            }
        }

        return response()->json(['data' => ['id' => $post->id]]);
    }

    public function publish(Request $request, Post $post): JsonResponse
    {
        abort_unless($request->user()->can('publish', $post), 403);

        $post->update([
            'status' => PostStatus::Published,
            'published_at' => $post->published_at ?? now(),
        ]);

        return response()->json(['data' => ['id' => $post->id, 'status' => PostStatus::Published->value]]);
    }

    public function unpublish(Request $request, Post $post): JsonResponse
    {
        abort_unless($request->user()->can('publish', $post), 403);

        $post->update(['status' => PostStatus::Draft]);

        return response()->json(['data' => ['id' => $post->id, 'status' => PostStatus::Draft->value]]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        abort_unless($request->user()->can('delete', $post), 403);

        $post->delete();

        return response()->json(['data' => ['id' => $post->id, 'deleted' => true]]);
    }

    private function recordRevision(Post $post, $user, array $tr): void
    {
        PostRevision::query()->create([
            'post_id' => $post->id,
            'editor_user_id' => $user->id,
            'locale' => $tr['locale'],
            'title' => $tr['title'],
            'body' => $tr['body'],
            'action' => $post->wasRecentlyCreated ? 'create' : 'update',
        ]);
    }

    private function summary(Post $post): array
    {
        return [
            'id' => $post->id,
            'type' => $post->type->value,
            'status' => $post->status->value,
            'is_featured' => $post->is_featured,
            'published_at' => $post->published_at?->toIso8601String(),
            'translations' => $post->translations->map(fn ($t) => [
                'locale' => $t->locale,
                'title' => $t->title,
                'slug' => $t->slug,
            ]),
        ];
    }

    private function detail(Post $post): array
    {
        return [
            'id' => $post->id,
            'type' => $post->type->value,
            'status' => $post->status->value,
            'is_featured' => $post->is_featured,
            'published_at' => $post->published_at?->toIso8601String(),
            'translations' => $post->translations->map(fn ($t) => [
                'locale' => $t->locale,
                'title' => $t->title,
                'slug' => $t->slug,
                'excerpt' => $t->excerpt,
                'body' => $t->sanitized_body,
            ]),
        ];
    }
}
