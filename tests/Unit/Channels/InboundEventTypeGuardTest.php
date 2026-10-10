<?php

use App\Actions\Channels\ImportChannelCancellation;
use App\Actions\Channels\ImportChannelOrder;
use App\Actions\Channels\ImportChannelReturn;
use App\DataObjects\Channels\ChannelInboundEvent;
use App\Models\SalesChannelAccount;
use Carbon\CarbonImmutable;

it('does not route an inbound event to the wrong local business transaction', function (string $class, string $eventType): void {
    $operation = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $account = new SalesChannelAccount;
    $event = new ChannelInboundEvent(
        eventType: $eventType,
        externalId: 'V4-TYPE-GUARD',
        occurredAt: CarbonImmutable::parse('2026-10-10'),
        data: [],
    );

    expect(fn () => $operation->handle($account, $event))->toThrow(DomainException::class);
})->with([
    'order handler with cancellation' => [ImportChannelOrder::class, 'cancel'],
    'order handler with return' => [ImportChannelOrder::class, 'return'],
    'cancellation handler with order' => [ImportChannelCancellation::class, 'order'],
    'cancellation handler with return' => [ImportChannelCancellation::class, 'return'],
    'return handler with order' => [ImportChannelReturn::class, 'order'],
    'return handler with cancellation' => [ImportChannelReturn::class, 'cancel'],
]);
