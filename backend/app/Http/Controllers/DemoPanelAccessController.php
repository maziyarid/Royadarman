<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\PanelDemoRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class DemoPanelAccessController extends Controller
{
    public function __invoke(Request $request, string $role): RedirectResponse
    {
        abort_unless((bool) config('royadarman.panel_demo_access'), 404);

        $identity = PanelDemoRegistry::identity($role);
        abort_unless($identity !== null, 404);

        $locale = (string) $request->query('locale', 'fa');
        abort_unless(in_array($locale, (array) config('royadarman.supported_locales', ['fa']), true), 404);

        $user = User::query()
            ->where('email', $identity['email'])
            ->where('role', $identity['role']->value)
            ->where('is_active', true)
            ->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put([
            'panel_demo' => true,
            'panel_demo_user_id' => (string) $user->id,
            'panel_demo_role' => $role,
        ]);
        $user->forceFill(['last_authenticated_at' => now()])->save();

        AuditEvent::query()->create([
            'actor_user_id' => $user->id,
            'action' => 'demo.panel.accessed',
            'resource_type' => User::class,
            'resource_id' => (string) $user->id,
            'result' => 'success',
            'context' => [
                'demo_role' => $role,
                'locale' => $locale,
                'request_id' => $request->attributes->get('request_id'),
            ],
            'correlation_id' => (string) Str::ulid(),
            'created_at' => now(),
        ]);

        return redirect()->route('panel', ['locale' => $locale]);
    }
}
