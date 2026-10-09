<?php

use App\Exceptions\DomainException as MarsDomainException;
use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\EnsureLocalNetwork;
use App\Http\Middleware\SetActiveCompany;
use App\Http\Middleware\SetActivePeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(CorrelationId::class);

        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        )));

        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies);
        }

        $middleware->alias([
            'local.network' => EnsureLocalNetwork::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'hooks/channel/*',
        ]);

        $middleware->appendToGroup('web', [
            AuthenticateSession::class,
            SetActiveCompany::class,
            SetActivePeriod::class,
        ]);

        $middleware->appendToPriorityList(StartSession::class, SetActiveCompany::class);
        $middleware->appendToPriorityList(SetActiveCompany::class, SetActivePeriod::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            MarsDomainException::class,
        ]);

        $exceptions->render(function (MarsDomainException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return response()->view('errors.domain', [
                'message' => $exception->getMessage(),
            ], 422);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            // Preserve Laravel's native 302/401/403/404/422 handling; only mask unexpected 500s.
            if ($exception instanceof HttpExceptionInterface
                || $exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException
                || $exception instanceof ModelNotFoundException
                || $exception instanceof ValidationException
                || config('app.debug')) {
                return null;
            }

            $code = (string) $request->attributes->get('correlation_id', '------');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'İşlem tamamlanamadı.',
                    'error_code' => $code,
                ], 500);
            }

            return response()->view('errors.unexpected', [
                'errorCode' => $code,
            ], 500);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->create();
