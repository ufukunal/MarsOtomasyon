<?php

use App\Actions\Production\CreateProductionOrdersForSalesOrder;
use App\Models\Period\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

it('opens one production draft for a confirmed sales order and returns the same ID when retried', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = IsolatedPostgres::productAndLocation();
        $db = DB::connection('period');
        $db->table('products')->where('id', $ids['product'])
            ->update(['channel_stock_mode' => 'production']);

        $recipeId = $db->table('production_recipes')->insertGetId([
            'product_id' => $ids['product'],
            'number' => 'V4-'.Str::random(12),
            'revision_no' => 1,
            'output_quantity' => '1.000',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = Document::query()->create([
            'document_type' => 'sales_order',
            'status' => 'confirmed',
            'document_date' => '2026-10-10',
        ]);
        $order->lines()->create([
            'line_no' => 1,
            'line_kind' => 'stock',
            'product_id' => $ids['product'],
            'unit_id' => $ids['unit'],
            'quantity' => '4.000',
            'base_quantity' => '4.000',
            'conversion_factor' => '1.000000',
            'unit_price' => '10.0000',
            'line_total' => '40.0000',
        ]);

        $make = app(CreateProductionOrdersForSalesOrder::class);
        $first = $make->handle($order);
        $again = $make->handle($order);

        expect($first)->toHaveCount(1)
            ->and($again)->toBe($first);
        $stored = $db->table('production_orders')
            ->where('source_sales_order_id', $order->id)->first();

        expect((int) $stored->recipe_id)->toBe($recipeId)
            ->and((string) $stored->planned_quantity)->toBe('4.000')
            ->and((string) $stored->status)->toBe('draft')
            ->and($db->table('production_orders')->where('source_sales_order_id', $order->id)->count())->toBe(1);
    });
});

it('refuses to create production orders for unconfirmed sales documents', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $order = new Document(['document_type' => 'sales_order', 'status' => 'draft']);
        $order->setRelation('lines', $order->lines()->getRelated()->newCollection());

        expect(fn () => app(CreateProductionOrdersForSalesOrder::class)->handle($order))
            ->toThrow(DomainException::class);
    });
});
