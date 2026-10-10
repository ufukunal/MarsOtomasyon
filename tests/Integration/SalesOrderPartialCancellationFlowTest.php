<?php

use App\Actions\Sales\CancelSalesOrderRemaining;
use App\Models\Period\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('cancels only selected sales order lines before closing a fully cancelled order', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $order = Document::query()->create([
                'document_type' => 'sales_order',
                'status' => 'confirmed',
                'document_date' => '2026-10-10',
            ]);
            $first = $order->lines()->create([
                'line_no' => 1,
                'line_kind' => 'service',
                'description' => 'Service One',
                'quantity' => '5.000',
                'unit_price' => '10.0000',
                'line_total' => '50.0000',
            ]);
            $second = $order->lines()->create([
                'line_no' => 2,
                'line_kind' => 'service',
                'description' => 'Service Two',
                'quantity' => '3.000',
                'unit_price' => '20.0000',
                'line_total' => '60.0000',
            ]);

            $action = app(CancelSalesOrderRemaining::class);
            $partial = $action->handle($order, 'v4-'.Str::random(20), [$first->id]);

            expect($partial->status)->toBe('confirmed')
                ->and((string) $first->refresh()->cancelled_quantity)->toBe('5.000')
                ->and((string) $second->refresh()->cancelled_quantity)->toBe('0.000');

            $closed = $action->handle($order, 'v4-'.Str::random(20), [$second->id]);

            expect($closed->status)->toBe('closed')
                ->and((string) $second->refresh()->cancelled_quantity)->toBe('3.000')
                ->and(DB::connection('period')->table('documents')->whereKey($order->id)->count())->toBe(1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('refuses to cancel a draft sales order and leaves its line quantity untouched', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $draft = Document::query()->create([
                'document_type' => 'sales_order', 'status' => 'draft', 'document_date' => '2026-10-10',
            ]);

            expect(fn () => app(CancelSalesOrderRemaining::class)->handle($draft, 'v4-'.Str::random(20)))
                ->toThrow(DomainException::class);

            expect($draft->refresh()->status)->toBe('draft');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
