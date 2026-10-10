<?php

use App\Support\Money\Money;
use PHPUnit\Framework\Assert;

it('bounds fixed-point arithmetic throughput without loading production infrastructure', function (): void {
    if (getenv('MARS_PERFORMANCE_TESTS_APPROVED') !== 'I_APPROVE_LOCAL_BENCHMARK') {
        Assert::markTestSkipped('Explicit, isolated microbenchmark approval is missing.');
    }

    $started = hrtime(true);
    $total = Money::of('0.0000');

    for ($i = 0; $i < 2500; $i++) {
        $total = $total->plus(Money::of('1.2500'));
    }

    $durationMs = (hrtime(true) - $started) / 1_000_000;

    expect($total->amount)->toBe('3125.0000')
        ->and($durationMs)->toBeLessThan(15000);
});
