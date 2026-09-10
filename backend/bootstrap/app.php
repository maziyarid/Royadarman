<?php

use App\Http\Middleware\EnsureStaffAccess;
use App\Http\Middleware\RequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: null,
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestId::class);
        $middleware->alias([
            'staff' => EnsureStaffAccess::class,
        ]);
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : '/fa/',
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'error.validation',
                    'message' => __('The given data was invalid.'),
                    'details' => $exception->errors(),
                ],
                'request_id' => $request->attributes->get('request_id'),
            ], $exception->status);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => ['code' => 'error.unauthenticated', 'message' => __('Unauthenticated.')],
                'request_id' => $request->attributes->get('request_id'),
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => ['code' => 'error.forbidden', 'message' => __('This action is unauthorized.')],
                'request_id' => $request->attributes->get('request_id'),
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => ['code' => 'error.not_found', 'message' => __('Not Found')],
                'request_id' => $request->attributes->get('request_id'),
            ], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $status = $exception->getStatusCode();
            $code = match ($status) {
                404 => 'error.not_found',
                429 => 'error.rate_limited',
                503 => 'error.unavailable',
                default => 'error.http.'.$status,
            };

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => $status >= 500 ? __('Server Error') : ($exception->getMessage() ?: __('Request failed.')),
                ],
                'request_id' => $request->attributes->get('request_id'),
            ], $status, $exception->getHeaders());
        });
    })->create();
