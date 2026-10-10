<?php

use App\Actions\Channels\CarryChannelPeriodState;

it('sorts marketplace order mappings without changing linked destination orders', function (): void {
    $subject = (new ReflectionClass(CarryChannelPeriodState::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(CarryChannelPeriodState::class, 'normalizeOrderMap');

    expect($method->invoke($subject, [9 => 29, 1 => 21, 5 => 25]))
        ->toBe([1 => 21, 5 => 25, 9 => 29]);
});

it('rejects duplicate, negative and missing IDs before marketplace period carry', function (array $map): void {
    $subject = (new ReflectionClass(CarryChannelPeriodState::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(CarryChannelPeriodState::class, 'normalizeOrderMap');

    expect(fn () => $method->invoke($subject, $map))->toThrow(DomainException::class);
})->with([
    [[1 => 3, 2 => 3]],
    [[0 => 1]],
    [[-1 => 2]],
    [[1 => 0]],
    [[1 => -2]],
]);
