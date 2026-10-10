<?php

use App\Actions\Channels\CarryChannelPeriodState;

function v4NormalizeChannelCarryMap(array $map): array
{
    $service = (new ReflectionClass(CarryChannelPeriodState::class))->newInstanceWithoutConstructor();

    return (new ReflectionMethod(CarryChannelPeriodState::class, 'normalizeOrderMap'))
        ->invoke($service, $map);
}

it('normalizes positive sales order IDs and sorts mappings numerically', function (): void {
    expect(v4NormalizeChannelCarryMap([30 => 300, 10 => 100, 20 => 200]))->toBe([
        10 => 100,
        20 => 200,
        30 => 300,
    ]);
});

it('preserves an empty carry mapping for a period with no open orders', function (): void {
    expect(v4NormalizeChannelCarryMap([]))->toBe([]);
});

it('rejects invalid source IDs, target IDs and non-injective mappings', function (array $mapping): void {
    expect(fn () => v4NormalizeChannelCarryMap($mapping))->toThrow(DomainException::class);
})->with([
    'zero source' => [[0 => 11]],
    'negative source' => [[-1 => 11]],
    'zero target' => [[1 => 0]],
    'negative target' => [[1 => -11]],
    'duplicate target' => [[1 => 12, 2 => 12]],
    'numeric zero string' => [['0' => '2']],
    'non-numeric source' => [['invalid' => 4]],
]);
