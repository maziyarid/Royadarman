<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\WorkspaceView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ProfileWorkspaceController extends Controller
{
    public function show(Request $request): View
    {
        return view('panel.profile', [
            ...WorkspaceView::data($request, 'profile'),
            'profileUser' => $request->user(),
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
}
