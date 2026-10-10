<?php

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelPayloadHasher;
use App\Support\Channels\ChannelSyncRecorder;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

function marsV4SyncChannel(int $companyId): SalesChannelAccount
{
    return SalesChannelAccount::query()->create([
        'company_id' => $companyId,
        'platform' => 'woocommerce',
        'name' => 'V4 local sync account',
        'credentials_encrypted' => [
            'consumer_key' => 'ck_v4_fake',
            'consumer_secret' => 'cs_v4_fake_private',
        ],
        'is_active' => true,
    ]);
}

it('coalesces duplicate queued marketplace sync events by business entity and action', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $account = marsV4SyncChannel($companyId);
        $recorder = app(ChannelSyncRecorder::class);
        $hasher = app(ChannelPayloadHasher::class);

        $firstHash = $hasher->hash(['sku' => 'V4-1', 'quantity' => 2]);
        $secondHash = $hasher->hash(['sku' => 'V4-1', 'quantity' => 3]);

        $first = $recorder->queue(
            $account->id, 'outbound', 'product', 'update', 123, 'V4-EXTERNAL', $firstHash,
            safeMetadata: ['sku' => 'V4-1', 'quantity' => 2],
        );
        $second = $recorder->queue(
            $account->id, 'outbound', 'product', 'update', 123, 'V4-EXTERNAL', $secondHash,
            safeMetadata: ['sku' => 'V4-1', 'quantity' => 3],
        );

        expect($first->id)->toBe($second->id)
            ->and($second->payload_hash)->toBe($secondHash)
            ->and($second->safe_metadata['quantity'])->toBe(3)
            ->and(DB::connection('period')->table('channel_sync_events')->count())->toBe(1);
    });
});

it('rejects duplicate processing claims before the provider request is attempted twice', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $account = marsV4SyncChannel($companyId);
        $recorder = app(ChannelSyncRecorder::class);

        $queued = $recorder->queue($account->id, 'outbound', 'listing', 'publish', 7, 'V4-777');
        $first = $recorder->startAttempt($queued);

        expect($first->status)->toBe('processing')
            ->and($first->attempts)->toBe(1);

        expect(fn () => $recorder->startAttempt($first))->toThrow(DomainException::class);
        expect($first->refresh()->attempts)->toBe(1);

        $done = $recorder->markSuccess($first, 'V4-EXTERNAL-777');
        expect($done->status)->toBe('success')
            ->and($done->external_id)->toBe('V4-EXTERNAL-777');
    });
});

it('rejects nested personally identifying and secret fields inside channel sync metadata', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $account = marsV4SyncChannel($companyId);
        $recorder = app(ChannelSyncRecorder::class);

        foreach ([
            ['api_key' => 'fake'],
            ['customer' => ['name' => 'Test']],
            ['data' => ['email' => 'v4@example.invalid']],
            ['recipient_address' => 'sample'],
            ['credentials' => 'v4-fake'],
        ] as $metadata) {
            expect(fn () => $recorder->queue(
                $account->id, 'outbound', 'order', 'sync', 1,
                safeMetadata: $metadata,
            ))->toThrow(DomainException::class);
        }

        expect(DB::connection('period')->table('channel_sync_events')->count())->toBe(0);
    });
});

it('refuses malformed SHA-256 fingerprints and unsupported sync directions before enqueuing', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $account = marsV4SyncChannel($companyId);
        $recorder = app(ChannelSyncRecorder::class);

        expect(fn () => $recorder->queue(
            $account->id, 'sideways', 'product', 'update', 1,
        ))->toThrow(DomainException::class);
        expect(fn () => $recorder->queue(
            $account->id, 'outbound', 'product', 'update', 1,
            payloadHash: 'not-an-allowed-hash',
        ))->toThrow(DomainException::class);
    });
});
