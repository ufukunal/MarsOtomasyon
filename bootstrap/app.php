<?php

use App\Foundation\Correlation\CorrelationIdMiddleware;
use App\Foundation\Health\ReadinessController;
use App\Modules\Core\Branch\ResolveActiveBranch;
use App\Modules\Core\Company\ResolveActiveCompany;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require base_path('routes/core-shell.php');
            require base_path('routes/accounts.php');
            require base_path('routes/products.php');
            require base_path('routes/quotes.php');
            require base_path('routes/sales-orders.php');
            Route::get('/health/ready', ReadinessController::class)->name('health.ready');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CorrelationIdMiddleware::class);
        $middleware->alias([
            'company.context' => ResolveActiveCompany::class,
            'branch.context' => ResolveActiveBranch::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->create();
