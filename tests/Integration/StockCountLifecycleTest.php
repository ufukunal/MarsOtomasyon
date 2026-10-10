<?php

use App\Actions\Stock\ReviewStockCount;
use App\Actions\Stock\SaveStockCountDraft;
use App\Actions\Stock\SaveStockCountLine;
use App\Actions\Stock\StartStockCount;
use App\Models\Period\StockCount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('captures frozen stock, permits count corrections and moves counts to review', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            DB::connection('period')->table('stock_balances')->insert([
                'product_id' => $ids['product'], 'location_id' => $ids['location'],
                'quantity' => '6.000', 'created_at' => now(), 'updated_at' => now(),
            ]);

            $count = app(SaveStockCountDraft::class)->handle([
                'location_id' => $ids['location'],
                'count_date' => '2026-10-10',
                'product_ids' => [$ids['product']],
            ]);

            expect($count->status)->toBe('draft');
            expect(fn () => app(ReviewStockCount::class)->handle($count->id))
                ->toThrow(DomainException::class);

            $started = app(StartStockCount::class)->handle($count->id, 'v4-'.Str::random(20));
            expect($started->status)->toBe('counting');
            expect($started->lines)->toHaveCount(1);
            $lineId = (int) $started->lines[0]->id;

            $line = app(SaveStockCountLine::class)->handle($count->id, $lineId, '4.000', 'short by two', true);
            expect($line->system_quantity)->toBe('6.000')
                ->and($line->counted_quantity)->toBe('4.000')
                ->and($line->difference)->toBe('-2.000')
                ->and($line->is_approved)->toBeTrue();

            $review = app(ReviewStockCount::class)->handle($count->id);
            expect($review->status)->toBe('review');
            expect(fn () => app(StartStockCount::class)->handle($count->id, 'v4-'.Str::random(20)))
                ->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('refuses negative inventory count and leaves stored counted quantity unchanged', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            $count = StockCount::query()->create([
                'location_id' => $ids['location'], 'count_date' => '2026-10-10', 'status' => 'counting',
            ]);
            $line = $count->lines()->create([
                'product_id' => $ids['product'], 'system_quantity' => '2.000',
                'counted_quantity' => '2.000', 'difference' => '0.000',
            ]);

            expect(fn () => app(SaveStockCountLine::class)->handle($count->id, $line->id, '-1'))
                ->toThrow(DomainException::class);
            expect($line->refresh()->counted_quantity)->toBe('2.000');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
