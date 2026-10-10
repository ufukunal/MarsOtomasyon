<?php

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelExternalEventRegistryService;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('reserves each external order once and replays an already completed import without duplicating it', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId, int $periodId): void {
        $db = DB::connection('master');
        $accountId = $db->table('sales_channel_accounts')->insertGetId([
            'company_id' => $companyId,
            'platform' => 'trendyol',
            'name' => 'V4 isolated account',
            'credentials_encrypted' => 'test-placeholder-never-read',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $account = new SalesChannelAccount;
        $account->id = $accountId;
        $registry = new ChannelExternalEventRegistryService;

        $first = $registry->reserve($account, 'order', 'ORDER-V4-100');
        expect($first->reserved)->toBeTrue();

        $registry->markDone($first->registryId, $periodId, 27);

        $second = $registry->reserve($account, 'order', 'ORDER-V4-100');
        expect($second->reserved)->toBeFalse()
            ->and($second->periodId)->toBe($periodId)
            ->and($second->periodDocumentId)->toBe(27);

        expect($db->table('channel_external_event_registry')
            ->where('channel_account_id', $accountId)
            ->where('external_id', 'ORDER-V4-100')
            ->count())->toBe(1);
    });
});

it('rejects a completed event rebound to a different period document', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId, int $periodId): void {
        $db = DB::connection('master');
        $accountId = $db->table('sales_channel_accounts')->insertGetId([
            'company_id' => $companyId,
            'platform' => 'n11',
            'name' => 'V4 replay check',
            'credentials_encrypted' => 'test-placeholder-never-read',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $account = new SalesChannelAccount;
        $account->id = $accountId;
        $registry = new ChannelExternalEventRegistryService;
        $reservation = $registry->reserve($account, 'return', 'RETURN-V4-22');
        $registry->markDone($reservation->registryId, $periodId, 25);

        expect(fn () => $registry->markDone($reservation->registryId, $periodId, 26))
            ->toThrow(DomainException::class);
    });
});
