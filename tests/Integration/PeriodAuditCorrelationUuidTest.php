<?php

use App\Http\Middleware\CorrelationId;
use App\Support\Audit\AuditContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\IsolatedPostgres;

it('persists a request correlation UUID in the period audit log', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $originalRequest = app('request');
        $request = Request::create('/uat-sales-order-confirm-audit');
        app()->instance('request', $request);

        try {
            $middleware = new CorrelationId;
            $response = $middleware->handle($request, function (): Response {
                AuditContext::period('Integration request audit UUID regression');

                return new Response('OK');
            });

            $uuid = $response->headers->get('X-Correlation-ID');

            expect($uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
                ->and(DB::connection('period')->table('activity_log')
                    ->where('description', 'Integration request audit UUID regression')
                    ->value('correlation_id'))->toBe($uuid);
        } finally {
            app()->instance('request', $originalRequest);
        }
    });
});

it('keeps the audit insert safe even when a legacy short correlation value is present', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $originalRequest = app('request');
        $request = Request::create('/uat-invalid-audit-id');
        $request->attributes->set('correlation_id', '5ACCD3');
        app()->instance('request', $request);

        try {
            AuditContext::period('Integration legacy short audit ID regression');

            expect(DB::connection('period')->table('activity_log')
                ->where('description', 'Integration legacy short audit ID regression')
                ->value('correlation_id'))->toBeNull();
        } finally {
            app()->instance('request', $originalRequest);
        }
    });
});
