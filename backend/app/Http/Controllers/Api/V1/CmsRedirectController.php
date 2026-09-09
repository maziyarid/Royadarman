<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cms\Redirect;
use App\Models\User;
use App\Policies\CmsContentPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CmsRedirectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $redirects = Redirect::query()->orderByDesc('updated_at')->paginate(50);

        return response()->json([
            'data' => $redirects->map(fn ($r) => [
                'id' => $r->id,
                'source_path' => $r->source_path,
                'destination_url' => $r->destination_url,
                'status_code' => $r->status_code,
                'is_active' => $r->is_active,
                'hit_count' => $r->hit_count,
            ]),
            'meta' => ['page' => $redirects->currentPage(), 'last' => $redirects->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'source_path' => ['required', 'string', 'max:500'],
            'destination_url' => ['required', 'string', 'max:500'],
            'status_code' => ['required', 'in:301,302'],
        ]);

        if (Redirect::query()->where('source_path', $data['source_path'])->exists()) {
            return response()->json(['error' => ['code' => 'redirect.duplicate_source']], 409);
        }

        $redirect = Redirect::query()->create($data);

        return response()->json(['data' => ['id' => $redirect->id]], 201);
    }

    public function update(Request $request, Redirect $redirect): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $data = $request->validate([
            'destination_url' => ['sometimes', 'string', 'max:500'],
            'status_code' => ['sometimes', 'in:301,302'],
            'is_active' => ['boolean'],
        ]);

        $redirect->update($data);

        return response()->json(['data' => ['id' => $redirect->id]]);
    }

    public function destroy(Request $request, Redirect $redirect): JsonResponse
    {
        abort_unless($this->canDelete($request->user()), 403);
        $redirect->delete();

        return response()->json(['data' => ['id' => $redirect->id, 'deleted' => true]]);
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
