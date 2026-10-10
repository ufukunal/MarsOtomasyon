<?php

use App\Actions\Channels\ImportChannelOrder;
use App\Actions\Channels\ProcessChannelInboundEvent;
use App\Actions\Channels\RetryChannelSyncEvent;
use App\DataObjects\Channels\ChannelInboundEvent;
use App\Models\Period\ChannelSyncEvent;
use App\Models\SalesChannelAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('refuses inbound orders of the wrong event type without a marketplace call', function (): void {
    $orderImporter = (new ReflectionClass(ImportChannelOrder::class))->newInstanceWithoutConstructor();
    $event = new ChannelInboundEvent(
        eventType: 'return',
        externalId: 'V4-RETURN',
        occurredAt: CarbonImmutable::parse('2026-10-10'),
        data: [],
    );
    expect(fn () => $orderImporter->handle(new SalesChannelAccount, $event))
        ->toThrow(DomainException::class);
});

it('rejects account events from another company before a sync row is created', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $processor = app(ProcessChannelInboundEvent::class);
        $account = new SalesChannelAccount;
        $account->company_id = $companyId + 1000;
        $event = new ChannelInboundEvent('order', 'V4-001', CarbonImmutable::parse('2026-10-10'), []);

        expect(fn () => $processor->handle($account, $event))
            ->toThrow(DomainException::class);
        expect(DB::connection('period')->table('channel_sync_events')->count())->toBe(0);
    });
});

it('rejects retries for successful, inbound and unlinked sync events without contacting providers', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $action = app(RetryChannelSyncEvent::class);
            foreach ([
                ['status' => 'success', 'direction' => 'outbound', 'entity_id' => 1],
                ['status' => 'failed', 'direction' => 'inbound', 'entity_id' => 1],
                ['status' => 'failed', 'direction' => 'outbound', 'entity_id' => null],
            ] as $invalid) {
                $event = new ChannelSyncEvent($invalid);
                expect(fn () => $action->handle($event))->toThrow(DomainException::class);
            }
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
