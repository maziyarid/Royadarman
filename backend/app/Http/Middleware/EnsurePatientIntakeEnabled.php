<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePatientIntakeEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('royadarman.intake_enabled')) {
            return new JsonResponse([
                'message' => 'ثبت درخواست واقعی هنوز فعال نشده است.',
                'code' => 'intake_not_enabled',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $next($request);
    }
}
