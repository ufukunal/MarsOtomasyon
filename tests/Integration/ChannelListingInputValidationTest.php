<?php

use App\Actions\Channels\SaveChannelProductListing;
use App\Models\SalesChannelAccount;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('rejects listing quantities below zero, missing production lead time and invalid manual stock', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        AuthorizedPeriod::login();

        try {
            $fixture = IsolatedPostgres::productAndLocation();
            $account = SalesChannelAccount::query()->create([
                'company_id' => $companyId,
                'platform' => 'trendyol',
                'name' => 'V4 local listing',
                'credentials_encrypted' => ['api_key' => 'v4-placeholder'],
                'is_active' => true,
            ]);
            $save = app(SaveChannelProductListing::class);

            foreach ([
                [[], ['stock_mode' => 'stock']],
                [[$fixture['location']], ['withhold_quantity' => '-1']],
                [[], ['stock_mode' => 'production', 'fixed_quantity' => '4']],
                [[], ['stock_mode' => 'manual']],
                [[], ['stock_mode' => 'manual', 'manual_quantity' => '-3']],
            ] as [$locationIds, $data]) {
                expect(fn () => $save->handle(
                    $account->id, $fixture['product'], $locationIds, $data,
                ))->toThrow(DomainException::class);
            }

            expect(DB::connection('period')->table('channel_product_listings')
                ->where('channel_account_id', $account->id)->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
