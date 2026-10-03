<?php

use App\Exceptions\DomainException as MarsDomainException;
use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\EnsureLocalNetwork;
use App\Http\Middleware\SetActivePeriod;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(CorrelationId::class);

        $middleware->alias([
            'local.network' => EnsureLocalNetwork::class,
        ]);

        $middleware->appendToGroup('web', [
            AuthenticateSession::class,
            SetActivePeriod::class,
        ]);
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
            if ($exception instanceof HttpExceptionInterface || config('app.debug')) {
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
