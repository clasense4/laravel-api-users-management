<?php

use App\Http\Middleware\AssignRequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            AssignRequestId::class,
        ]);
        $middleware->statefulApi();
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn () => throw new AuthenticationException('Unauthenticated.'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always render JSON for API routes
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Safe unexpected error response for API consumers
        $exceptions->render(function (Throwable $e, Request $request): ?Response {
            if (! $request->is('api/*')) {
                return null;
            }

            // Let Laravel handle auth, validation, and HTTP exceptions normally
            if (
                $e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof HttpException
            ) {
                return null;
            }

            $requestId = $request->attributes->get('request_id', (string) Str::uuid());

            Log::error('Unhandled API exception', [
                'request_id' => $requestId,
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
                'exception_class' => $e::class,
                'deployment_version' => config('app.version', 'unknown'),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred.',
                'error_code' => 'INTERNAL_ERROR',
                'request_id' => $requestId,
            ], 500);
        });
    })->create();
