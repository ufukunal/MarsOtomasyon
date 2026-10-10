<?php

use App\Actions\Channels\SyncChannelShipmentStatus;
use App\Models\Period\ChannelOrderSnapshot;
use App\Models\Period\Document;
use App\Models\SalesChannelAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('refuses shipment pushes with no external package ID before an API call or sync queue event', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        AuthorizedPeriod::login();
        Http::preventStrayRequests();
        Http::fake();

        try {
            $account = SalesChannelAccount::query()->create([
                'company_id' => $companyId,
                'platform' => 'trendyol',
                'name' => 'V4 local shipment source',
                'credentials_encrypted' => [],
                'is_active' => true,
            ]);
            $order = Document::query()->create([
                'document_type' => 'sales_order',
                'document_date' => '2026-10-10',
                'status' => 'confirmed',
            ]);
            ChannelOrderSnapshot::query()->create([
                'sales_order_id' => $order->id,
                'channel_account_id' => $account->id,
                'external_order_id' => 'V4-ORDER-200',
                'external_package_id' => null,
            ]);

            expect(fn () => app(SyncChannelShipmentStatus::class)->handle($order, 'SHIPPED'))
                ->toThrow(DomainException::class);
            expect(DB::connection('period')->table('channel_sync_events')->count())->toBe(0);
            Http::assertNothingSent();
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
