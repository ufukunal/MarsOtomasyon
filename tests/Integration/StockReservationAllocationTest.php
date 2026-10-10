<?php

use App\Actions\Stock\AdjustReservedBalance;
use App\Actions\Stock\ReserveStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

function marsStockReservationFixture(): array
{
    $ids = IsolatedPostgres::productAndLocation();
    $db = DB::connection('period');
    $suffix = strtolower(Str::random(10));
    $nextLocation = $db->table('locations')->insertGetId([
        'code' => 'L'.$suffix,
        'name' => 'Second location '.$suffix,
        'kind' => 'warehouse',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ([
        [$ids['location'], '2.000'],
        [$nextLocation, '3.000'],
    ] as [$locationId, $quantity]) {
        $db->table('stock_balances')->insert([
            'product_id' => $ids['product'],
            'location_id' => $locationId,
            'quantity' => $quantity,
            'reserved' => '0.000',
            'consignment_reserved' => '0.000',
            'quarantine' => '0.000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return [$ids['product'], (int) $ids['location'], (int) $nextLocation];
}

it('distributes partial allocations across preferred locations while recording each reservation', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        [$productId, $first, $second] = marsStockReservationFixture();
        $method = new ReflectionMethod(ReserveStock::class, 'reserve');
        $result = $method->invoke(
            new ReserveStock(new AdjustReservedBalance),
            $productId,
            '4.000',
            [$first, $second],
            'sales_order',
            91,
            92,
            null,
            null,
        );

        expect($result['reserved_quantity'])->toBe('4.000')
            ->and($result['open_quantity'])->toBe('0.000')
            ->and($result['reservation_ids'])->toHaveCount(2);

        $db = DB::connection('period');
        expect((string) $db->table('stock_balances')->where('location_id', $first)->value('reserved'))->toBe('2.000')
            ->and((string) $db->table('stock_balances')->where('location_id', $second)->value('reserved'))->toBe('2.000')
            ->and($db->table('stock_reservations')->where('document_id', 91)->count())->toBe(2);
    });
});

it('keeps the unfilled order quantity open instead of allocating nonexistent stock', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        [$productId, $first, $second] = marsStockReservationFixture();
        $method = new ReflectionMethod(ReserveStock::class, 'reserve');
        $result = $method->invoke(
            new ReserveStock(new AdjustReservedBalance),
            $productId,
            '8.000',
            [$first, $second],
            'sales_order',
            93,
            94,
            null,
            null,
        );

        expect($result['reserved_quantity'])->toBe('5.000')
            ->and($result['open_quantity'])->toBe('3.000');
    });
});

it('rejects over-reservations and negative reservations before changing balances', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        [$productId, $first] = marsStockReservationFixture();
        $adjust = new AdjustReservedBalance;

        expect(fn () => $adjust->handle($productId, $first, '3.000'))
            ->toThrow(DomainException::class);
        expect(fn () => $adjust->handle($productId, $first, '-0.001'))
            ->toThrow(DomainException::class);

        expect((string) DB::connection('period')->table('stock_balances')
            ->where('product_id', $productId)
            ->where('location_id', $first)
            ->value('reserved'))->toBe('0.000');
    });
});
