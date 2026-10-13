<?php

use App\Actions\Sales\ReserveSalesOrderLines;
use App\Models\Period\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('reserves stock for a confirmed sales order and repeated requests do not duplicate stock', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();
        try {
            $ids = IsolatedPostgres::productAndLocation();
            DB::connection('period')->table('stock_balances')->insert([
                'product_id' => $ids['product'],
                'location_id' => $ids['location'],
                'quantity' => '5.000',
                'reserved' => '0.000',
                'consignment_reserved' => '0.000',
                'quarantine' => '0.000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $order = Document::query()->create([
                'document_type' => 'sales_order',
                'status' => 'confirmed',
                'document_date' => '2026-10-10',
            ]);
            $line = $order->lines()->create([
                'line_no' => 1,
                'line_kind' => 'stock',
                'product_id' => $ids['product'],
                'unit_id' => $ids['unit'],
                'location_id' => $ids['location'],
                'quantity' => '2.000',
                'conversion_factor' => '1.000000',
                'base_quantity' => '2.000',
                'unit_price' => '10.0000',
                'line_total' => '20.0000',
            ]);
            $reserve = app(ReserveSalesOrderLines::class);
            $first = $reserve->handle($order, [$line->id => [$ids['location']]], (string) Str::uuid());
            $second = $reserve->handle($order, [$line->id => [$ids['location']]], (string) Str::uuid());

            expect($first[$line->id]['reserved_quantity'])->toBe('2.000')
                ->and($first[$line->id]['open_quantity'])->toBe('0.000')
                ->and($second)->toBe([])
                ->and((string) DB::connection('period')->table('stock_balances')
                    ->where('product_id', $ids['product'])->where('location_id', $ids['location'])
                    ->value('reserved'))->toBe('2.000')
                ->and(DB::connection('period')->table('stock_reservations')
                    ->where('document_line_id', $line->id)->where('status', 'active')
                    ->count())->toBe(1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
