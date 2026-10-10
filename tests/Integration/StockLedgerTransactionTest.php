<?php

use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;
use App\Exceptions\NegativeStockException;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('posts a receipt then shipment and preserves the stock ledger and moving average', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $fixture = IsolatedPostgres::productAndLocation();
        $post = app(RecordStockMovement::class);
        $receipt = $post->handle(new StockMovementData(
            productId: $fixture['product'], locationId: $fixture['location'],
            movementDate: '2026-10-10', direction: 'in', reason: 'purchase',
            quantity: '5.000', unitCost: '10.0000', updatesAverage: true,
        ));
        $issue = $post->handle(new StockMovementData(
            productId: $fixture['product'], locationId: $fixture['location'],
            movementDate: '2026-10-10', direction: 'out', reason: 'sale',
            quantity: '2.000',
        ));

        $db = DB::connection('period');
        $balance = $db->table('stock_balances')->where('product_id', $fixture['product'])->first();
        $cost = $db->table('product_costs')->where('product_id', $fixture['product'])->first();
        expect((string) $balance->quantity)->toBe('3.000')
            ->and((string) $cost->moving_average)->toBe('10.0000')
            ->and((string) $receipt->total_cost)->toBe('50.0000')
            ->and((string) $issue->total_cost)->toBe('20.0000')
            ->and($db->table('stock_movements')->where('product_id', $fixture['product'])->count())->toBe(2);
    });
});

it('rolls back a shipment that would make stock negative', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $fixture = IsolatedPostgres::productAndLocation();
        $db = DB::connection('period');
        $db->table('stock_balances')->insert([
            'product_id' => $fixture['product'], 'location_id' => $fixture['location'],
            'quantity' => '1.000', 'reserved' => '0.000',
            'consignment_reserved' => '0.000', 'quarantine' => '0.000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        expect(fn () => app(RecordStockMovement::class)->handle(new StockMovementData(
            productId: $fixture['product'], locationId: $fixture['location'],
            movementDate: '2026-10-10', direction: 'out', reason: 'sale',
            quantity: '2.000',
        )))->toThrow(NegativeStockException::class);

        expect($db->table('stock_movements')->where('product_id', $fixture['product'])->count())->toBe(0)
            ->and((string) $db->table('stock_balances')->where('product_id', $fixture['product'])->value('quantity'))->toBe('1.000');
    });
});
