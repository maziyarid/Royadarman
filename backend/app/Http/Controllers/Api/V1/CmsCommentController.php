<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\CMS\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Models\Cms\Comment;
use App\Models\User;
use App\Policies\CmsContentPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CmsCommentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,spam,trash'],
            'post_id' => ['nullable', 'integer'],
        ]);

        $comments = Comment::query()
            ->with(['post.translations', 'author'])
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['post_id'] ?? null, fn ($q, $p) => $q->where('post_id', $p))
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json([
            'data' => $comments->map(fn ($c) => $this->summary($c)),
            'meta' => ['page' => $comments->currentPage(), 'last' => $comments->lastPage()],
        ]);
    }

    public function moderate(Request $request, Comment $comment): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,spam,trash'],
        ]);

        $comment->update(['status' => $data['status']]);

        return response()->json(['data' => ['id' => $comment->id, 'status' => $data['status']]]);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        abort_unless($this->canDelete($request->user()), 403);
        $comment->delete();

        return response()->json(['data' => ['id' => $comment->id, 'deleted' => true]]);
    }

    private function summary(Comment $c): array
    {
        return [
            'id' => $c->id,
            'post_id' => $c->post_id,
            'author_name' => $c->author_name ?? optional($c->author)->name,
            'body' => $c->body,
            'status' => $c->status instanceof CommentStatus ? $c->status->value : $c->status,
            'parent_id' => $c->parent_id,
            'created_at' => $c->created_at,
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
