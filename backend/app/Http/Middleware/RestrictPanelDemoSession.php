<?php

namespace App\Http\Middleware;

use App\Models\HomeServiceRequest;
use App\Models\PatientCase;
use App\Models\SupportConversation;
use App\Support\PanelDemoRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RestrictPanelDemoSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! (bool) $request->session()->get('panel_demo', false)) {
            return $next($request);
        }

        if ($request->routeIs('demo.panel.access')) {
            return $next($request);
        }

        $user = $request->user();
        $expectedUserId = (string) $request->session()->get('panel_demo_user_id', '');

        if (! (bool) config('royadarman.panel_demo_access') || ! $user || (string) $user->id !== $expectedUserId) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Demo session is no longer valid.');
        }

        if ($request->is('api/v1/auth/logout') && $request->isMethod('POST')) {
            return $next($request);
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            abort(403, 'Demo sessions are restricted to the read-only role panel.');
        }

        if ($request->routeIs('panel', 'dashboard', 'panel.profile', 'panel.support.index', 'panel.home-service.index', 'patient.request.create')) {
            return $next($request);
        }

        if ($request->routeIs('panel.case')) {
            $this->assertDemoCase($request);

            return $next($request);
        }

        if ($request->routeIs('panel.support.show')) {
            $this->assertDemoSupport($request);

            return $next($request);
        }

        if ($request->routeIs('panel.home-service.show')) {
            $this->assertDemoHomeService($request);

            return $next($request);
        }

        abort(403, 'Demo sessions are restricted to the read-only role panel.');
    }

    private function assertDemoCase(Request $request): void
    {
        $case = $request->route('case');
        $reference = $case instanceof PatientCase
            ? (string) $case->public_reference
            : (string) (PatientCase::query()->find($case)?->public_reference ?? '');

        abort_unless(
            PanelDemoRegistry::isDemoCaseReference($reference),
            403,
            'Demo sessions cannot open non-test cases.',
        );
    }

    private function assertDemoSupport(Request $request): void
    {
        $conversation = $request->route('conversation');
        if (! $conversation instanceof SupportConversation) {
            $conversation = SupportConversation::query()->find($conversation);
        }
        abort_unless($conversation, 403, 'Demo sessions cannot open non-test support threads.');

        if ($conversation->case_id) {
            $reference = (string) (PatientCase::query()->find($conversation->case_id)?->public_reference ?? '');
            abort_unless(
                PanelDemoRegistry::isDemoCaseReference($reference),
                403,
                'Demo sessions cannot open non-test support threads.',
            );
        }

        $demoEmails = array_column(PanelDemoRegistry::identities(), 'email');
        $patientEmail = (string) ($conversation->patient?->email ?? $conversation->patient()->value('email'));
        abort_unless(
            in_array($patientEmail, $demoEmails, true),
            403,
            'Demo sessions cannot open non-test support threads.',
        );
    }

    private function assertDemoHomeService(Request $request): void
    {
        $home = $request->route('homeService');
        if (! $home instanceof HomeServiceRequest) {
            $home = HomeServiceRequest::query()->find($home);
        }
        abort_unless($home, 403, 'Demo sessions cannot open non-test home-service records.');

        $reference = (string) (PatientCase::query()->find($home->case_id)?->public_reference ?? '');
        abort_unless(
            PanelDemoRegistry::isDemoCaseReference($reference),
            403,
            'Demo sessions cannot open non-test home-service records.',
        );
    }
}
