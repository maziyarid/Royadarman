<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class DemoPanelAccessController extends Controller
{
    public function __invoke(Request $request, string $role): RedirectResponse
    {
        abort_unless((bool) config('royadarman.panel_demo_access'), 404);

        $email = match ($role) {
            'admin' => 'demo-owner@royadarman.invalid',
            'client' => 'demo-patient@royadarman.invalid',
            'clinic' => 'demo-clinic@royadarman.invalid',
            default => abort(404),
        };

        $user = User::query()->where('email', $email)->where('is_active', true)->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('panel', ['locale' => 'fa']);
    }
}
