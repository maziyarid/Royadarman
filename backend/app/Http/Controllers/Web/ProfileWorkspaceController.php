<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Services\SessionInventoryService;
use App\Http\Controllers\Controller;
use App\Support\WorkspaceView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class ProfileWorkspaceController extends Controller
{
    public function __construct(private readonly SessionInventoryService $sessions) {}

    public function show(Request $request): View
    {
        $currentId = $request->hasSession() ? (string) $request->session()->getId() : null;
        $sessionRows = $this->sessions->listForUser($request->user(), $currentId);

        return view('panel.profile', [
            ...WorkspaceView::data($request, 'profile'),
            'profileUser' => $request->user(),
            'sessions' => $sessionRows,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'in:fa,ar,en'],
            'name' => ['nullable', 'string', 'max:80'],
        ]);

        $request->user()->update($data);

        return redirect()
            ->route('panel.profile', ['locale' => $data['locale']])
            ->with('status', __('panel.saved'));
    }

    public function revokeSession(Request $request, string $session): RedirectResponse
    {
        $user = $request->user();
        $currentId = (string) $request->session()->getId();

        if (hash_equals($currentId, $session)) {
            return redirect()
                ->route('panel.profile', ['locale' => app()->getLocale()])
                ->with('status', __('panel.sessions.cannot_revoke_current'));
        }

        $this->sessions->revokeOne($user, $session);

        return redirect()
            ->route('panel.profile', ['locale' => app()->getLocale()])
            ->with('status', __('panel.sessions.revoked_one'));
    }

    public function revokeOthers(Request $request): RedirectResponse
    {
        $user = $request->user();
        $currentId = (string) $request->session()->getId();
        $deleted = $this->sessions->revokeOthers($user, $currentId);

        return redirect()
            ->route('panel.profile', ['locale' => app()->getLocale()])
            ->with('status', __('panel.sessions.revoked_others', ['count' => $deleted]));
    }

    public function revokeAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->sessions->revokeAll($user, $user->id, 'user_revoke_all');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $locale = app()->getLocale();
        $home = $locale === 'fa' ? route('public.home.fa') : route('public.home', ['locale' => $locale]);

        return redirect($home)->with('status', __('panel.sessions.revoked_all'));
    }
}
