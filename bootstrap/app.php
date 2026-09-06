<?php

use App\Foundation\Correlation\CorrelationIdMiddleware;
use App\Foundation\Health\ReadinessController;
use App\Foundation\Operations\EnforceProductionSafety;
use App\Modules\B2B\Http\Middleware\EnsureB2BAccess;
use App\Modules\Communication\Http\Middleware\ApiTokenRateLimit;
use App\Modules\Communication\Http\Middleware\AuthenticateApiAccessToken;
use App\Modules\Communication\Http\Middleware\AuthenticateScannerAgent;
use App\Modules\Communication\Http\Middleware\IdempotentApiWrite;
use App\Modules\Communication\Http\Middleware\RequireApiPermission;
use App\Modules\Core\Branch\ResolveActiveBranch;
use App\Modules\Core\Company\ResolveActiveCompany;
use App\Modules\Operations\EnforceCompanyIpPolicy;
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
            require base_path('routes/product-families.php');
            require base_path('routes/product-installation.php');
            require base_path('routes/quotes.php');
            require base_path('routes/sales-orders.php');
            require base_path('routes/dispatches.php');
            require base_path('routes/sales-invoices.php');
            require base_path('routes/sales-returns.php');
            require base_path('routes/purchase-orders.php');
            require base_path('routes/goods-receipts.php');
            require base_path('routes/supplier-invoices.php');
            require base_path('routes/purchase-returns.php');
            require base_path('routes/treasury.php');
            require base_path('routes/instruments.php');
            require base_path('routes/reports.php');
            require base_path('routes/production.php');
            require base_path('routes/subcontract.php');
            require base_path('routes/imports.php');
            require base_path('routes/operations.php');
            require base_path('routes/b2b.php');
            require base_path('routes/labels.php');
            require base_path('routes/mobile-warehouse.php');
            Route::middleware('api')->prefix('api/v1')->group(base_path('routes/api-v1.php'));
            Route::get('/health/ready', ReadinessController::class)->name('health.ready');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CorrelationIdMiddleware::class);
        $middleware->append(EnforceProductionSafety::class);
        $middleware->alias([
            'company.context' => ResolveActiveCompany::class,
            'branch.context' => ResolveActiveBranch::class,
            'security.ip' => EnforceCompanyIpPolicy::class,
            'b2b.auth' => EnsureB2BAccess::class,
            'api.token' => AuthenticateApiAccessToken::class,
            'api.permission' => RequireApiPermission::class,
            'api.rate' => ApiTokenRateLimit::class,
            'api.idempotent' => IdempotentApiWrite::class,
            'scanner.auth' => AuthenticateScannerAgent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());
    })
    ->create();
