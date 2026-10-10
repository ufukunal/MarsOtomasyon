<?php

use App\Actions\Production\LinkProductionServiceInvoice;
use App\Actions\Production\ReverseProductionCompletion;
use App\Models\Period\Document;
use App\Models\Period\ProductionCompletion;
use App\Models\Period\ProductionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('rejects missing reversal explanations before mutating production inventory or costs', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $before = DB::connection('period')->table('stock_movements')->count();
            expect(fn () => app(ReverseProductionCompletion::class)->handle(
                new ProductionCompletion, '2026-10-10', ' ', 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);
            expect(DB::connection('period')->table('stock_movements')->count())->toBe($before);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('refuses attaching a subcontract service invoice to an internal production order', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $fixture = IsolatedPostgres::productAndLocation();
            $recipe = DB::connection('period')->table('production_recipes')->insertGetId([
                'product_id' => $fixture['product'],
                'number' => 'R-'.Str::random(12),
                'revision_no' => 1,
                'output_quantity' => '1.000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $orderId = DB::connection('period')->table('production_orders')->insertGetId([
                'product_id' => $fixture['product'],
                'recipe_id' => $recipe,
                'recipe_revision_no' => 1,
                'document_date' => '2026-10-10',
                'planned_quantity' => '2.000',
                'production_type' => 'internal',
                'status' => 'confirmed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $invoiceId = DB::connection('period')->table('documents')->insertGetId([
                'document_type' => 'supplier_invoice',
                'document_date' => '2026-10-10',
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            expect(fn () => app(LinkProductionServiceInvoice::class)->handle(
                ProductionOrder::query()->findOrFail($orderId),
                Document::query()->findOrFail($invoiceId),
                'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);
            expect(DB::connection('period')->table('production_service_invoices')->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
