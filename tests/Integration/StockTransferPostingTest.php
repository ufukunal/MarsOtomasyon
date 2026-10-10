<?php

use App\Actions\Stock\CancelTransfer;
use App\Actions\Stock\ReceiveTransfer;
use App\Actions\Stock\RecordStockMovement;
use App\Actions\Stock\SendTransfer;
use App\DataObjects\StockMovementData;
use App\Models\Period\Transfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

function marsV4TransferFixture(): array
{
    $ids = IsolatedPostgres::productAndLocation();
    $db = DB::connection('period');
    $target = (int) $db->table('locations')->insertGetId([
        'code' => 'T-'.Str::random(10), 'name' => 'V4 Receiving Depot',
        'kind' => 'warehouse', 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $ids['product'], locationId: $ids['location'],
        movementDate: '2026-10-10', direction: 'in', reason: 'opening',
        quantity: '10.000', unitCost: '4.0000', updatesAverage: true,
    ));
    $transfer = Transfer::query()->create([
        'from_location_id' => $ids['location'], 'to_location_id' => $target,
        'transfer_date' => '2026-10-10', 'status' => 'draft',
    ]);
    $line = $transfer->lines()->create([
        'product_id' => $ids['product'], 'quantity' => '6.000', 'received_quantity' => '0.000',
    ]);

    return [$transfer, $line, $ids['product'], $ids['location'], $target];
}

it('sends a transfer, partially receives it and completes receipt without duplicating stock', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            [$transfer, $line, $productId, $source, $target] = marsV4TransferFixture();

            $sent = app(SendTransfer::class)->handle($transfer->id, 'v4-'.Str::random(20));
            expect($sent->status)->toBe('in_transit');

            $part = app(ReceiveTransfer::class)->handle($transfer->id, [$line->id => '2.000'], 'v4-'.Str::random(20));
            expect($part->status)->toBe('partially_received');

            expect(fn () => app(ReceiveTransfer::class)->handle(
                $transfer->id, [$line->id => '5.000'], 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);
            expect($line->refresh()->received_quantity)->toBe('2.000');

            $done = app(ReceiveTransfer::class)->handle($transfer->id, [$line->id => '4.000'], 'v4-'.Str::random(20));
            expect($done->status)->toBe('received');
            expect($line->refresh()->received_quantity)->toBe('6.000');

            $balances = DB::connection('period')->table('stock_balances')
                ->where('product_id', $productId)->pluck('quantity', 'location_id')->all();

            expect((string) $balances[$source])->toBe('4.000')
                ->and((string) $balances[$target])->toBe('6.000');

            expect(fn () => app(CancelTransfer::class)->handle(
                $transfer->id, 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('returns outstanding transit stock to its original location when cancellation occurs', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            [$transfer, $line, $productId, $source, $target] = marsV4TransferFixture();

            app(SendTransfer::class)->handle($transfer->id, 'v4-'.Str::random(20));
            app(ReceiveTransfer::class)->handle($transfer->id, [$line->id => '2.000'], 'v4-'.Str::random(20));
            $cancelled = app(CancelTransfer::class)->handle($transfer->id, 'v4-'.Str::random(20));

            expect($cancelled->status)->toBe('cancelled');

            $balances = DB::connection('period')->table('stock_balances')
                ->where('product_id', $productId)->pluck('quantity', 'location_id')->all();

            expect((string) $balances[$source])->toBe('8.000')
                ->and((string) $balances[$target])->toBe('2.000');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('forbids sending a transfer to itself and preserves stock upon failure', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            [$transfer, $line, $productId, $source] = marsV4TransferFixture();
            $transfer->to_location_id = $source;
            $transfer->save();

            expect(fn () => app(SendTransfer::class)->handle($transfer->id, 'v4-'.Str::random(20)))
                ->toThrow(DomainException::class);
            expect($transfer->refresh()->status)->toBe('draft');
            expect(DB::connection('period')->table('stock_movements')->where('product_id', $productId)
                ->where('reason', 'transfer')->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
