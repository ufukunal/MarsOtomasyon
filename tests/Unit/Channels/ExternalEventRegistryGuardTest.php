<?php

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelExternalEventRegistryService;

it('refuses unknown inbound event types before accessing master PostgreSQL', function (string $type): void {
    $registry = new ChannelExternalEventRegistryService;
    $account = new SalesChannelAccount;

    expect(fn () => $registry->reserve($account, $type, 'ORDER-123'))
        ->toThrow(DomainException::class);
})->with(['', 'delete', 'settlement', 'order.cancelled', 'refund']);

it('requires a nonempty external event identity before registration', function (string $externalId): void {
    $registry = new ChannelExternalEventRegistryService;
    $account = new SalesChannelAccount;

    expect(fn () => $registry->reserve($account, 'order', $externalId))
        ->toThrow(DomainException::class);
})->with(['', ' ', "\t\n"]);

it('rejects missing, zero, identical and negative carry provenance identifiers', function (array $ids): void {
    $registry = new ChannelExternalEventRegistryService;
    $account = new SalesChannelAccount;

    expect(fn () => $registry->rebindCarriedOrder($account, 'O-1', ...$ids))
        ->toThrow(DomainException::class);
})->with([
    'missing source period' => [[0, 1, 2, 3]],
    'missing source document' => [[1, 0, 2, 3]],
    'missing target period' => [[1, 2, 0, 3]],
    'missing target document' => [[1, 2, 3, 0]],
    'same period' => [[1, 2, 1, 3]],
    'negative period' => [[-1, 2, 3, 4]],
]);
