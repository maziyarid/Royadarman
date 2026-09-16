<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Enums\UserRole;
use App\Support\PanelDemoRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class PresentationPortalController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless($this->enabled(), 404);

        $locale = (string) $request->query('locale', 'fa');
        if (! in_array($locale, ['fa', 'ar', 'en'], true)) {
            $locale = 'fa';
        }
        app()->setLocale($locale);

        $handoffs = [];
        if ((bool) config('royadarman.panel_demo_access')) {
            foreach (PanelDemoRegistry::aliases() as $alias) {
                $handoffs[$alias] = URL::temporarySignedRoute(
                    'demo.panel.access',
                    now()->addMinutes(30),
                    ['role' => $alias, 'locale' => $locale],
                );
            }
        }

        $user = $request->user();
        $canReseed = $user !== null
            && in_array($user->role, [UserRole::Owner, UserRole::TechnicalAdministrator], true)
            && (bool) config('royadarman.panel_demo_access');

        return response()
            ->view('public.pres', [
                'locale' => $locale,
                'pageKey' => 'pres',
                'handoffs' => $handoffs,
                'canReseed' => $canReseed,
                'gaps' => $this->gaps(),
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function reseed(Request $request): RedirectResponse
    {
        abort_unless($this->enabled(), 404);
        abort_unless($request->user() !== null, 403);
        abort_unless(in_array($request->user()->role, [UserRole::Owner, UserRole::TechnicalAdministrator], true), 403);
        abort_unless((bool) config('royadarman.panel_demo_access'), 404);

        $exit = Artisan::call('royadarman:panel-demo:seed', ['--force' => true]);
        abort_if($exit !== 0, HttpResponse::HTTP_SERVICE_UNAVAILABLE);

        return redirect()
            ->route('public.pres')
            ->with('status', __('ui.pres.reset_done'));
    }

    private function enabled(): bool
    {
        if ((bool) config('royadarman.panel_demo_access')) {
            return true;
        }

        return ! app()->environment('production');
    }

    /** @return list<array{area: string, existing: string, missing: string, risk: string}> */
    private function gaps(): array
    {
        return [
            ['area' => 'Homepage', 'existing' => 'Blade home now service-first', 'missing' => 'Guided 9-step request in Blade', 'risk' => 'High'],
            ['area' => 'Patient journey', 'existing' => 'Single form; intake often disabled', 'missing' => 'Progressive flow behind OTP', 'risk' => 'High'],
            ['area' => 'Dashboards', 'existing' => 'Stat + list workspaces', 'missing' => 'Task-oriented SLA cards', 'risk' => 'High'],
            ['area' => '/pres/', 'existing' => 'This portal; signed 30-minute role handoffs', 'missing' => 'Operator reseed UX polish', 'risk' => 'Med'],
            ['area' => 'CMS media', 'existing' => 'Byte sniff + re-encode sanitizer', 'missing' => 'Dedicated CmsMediaPolicy', 'risk' => 'Med'],
            ['area' => 'Sessions', 'existing' => 'Laravel DB sessions', 'missing' => 'Inventory/revoke UI', 'risk' => 'Med'],
            ['area' => 'Discovery', 'existing' => 'Clinic records', 'missing' => 'Suitability + Haversine', 'risk' => 'Med'],
            ['area' => 'Referral SLA', 'existing' => 'Proposal/grant models', 'missing' => 'Append-only lifecycle UI', 'risk' => 'Med'],
        ];
    }
}
