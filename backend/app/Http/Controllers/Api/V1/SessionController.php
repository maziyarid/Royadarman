<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\SessionInventoryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class SessionController extends Controller
{
    public function __construct(private readonly SessionInventoryService $sessions) {}

    public function index(Request $request): JsonResponse
    {
        $currentId = $request->hasSession() ? (string) $request->session()->getId() : null;
        $rows = $this->sessions->listForUser($request->user(), $currentId);

        return response()->json(['data' => ['sessions' => $rows->values()]]);
    }

    public function destroy(Request $request, string $session): JsonResponse
    {
        $user = $request->user();
        $currentId = $request->hasSession() ? (string) $request->session()->getId() : '';

        if ($currentId !== '' && hash_equals($currentId, $session)) {
            return response()->json([
                'error' => [
                    'code' => 'session.cannot_revoke_current',
                    'message' => __('panel.sessions.cannot_revoke_current'),
                ],
            ], 422);
        }

        $ok = $this->sessions->revokeOne($user, $session);

        return response()->json(['data' => ['revoked' => $ok]], $ok ? 200 : 404);
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        $currentId = (string) $request->session()->getId();
        $deleted = $this->sessions->revokeOthers($request->user(), $currentId);

        return response()->json(['data' => ['revoked_count' => $deleted]]);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $deleted = $this->sessions->revokeAll($user, $user->id, 'user_revoke_all');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => ['revoked_count' => $deleted, 'logged_out' => true]]);
    }
}
