<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Services\SessionAssurance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRecentAuthentication
{
    public function __construct(private readonly SessionAssurance $assurance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $session = $request->hasSession() ? $request->session() : null;

        abort_unless(
            $user
            && $user->is_active
            && $session !== null
            && ! $session->get('panel_demo', false),
            403,
        );

        // Proof is per session. users.last_authenticated_at is shared by every
        // device of the account and must not refresh another session.
        abort_unless(
            $this->assurance->isFresh($session),
            423,
            __('panel.security.reauthenticate'),
        );

        return $next($request);
    }
}
