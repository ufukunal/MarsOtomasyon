<?php

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use RuntimeException;

function marsValkeyTestApproval(): void
{
    if (getenv('MARS_INTEGRATION_TESTS_APPROVED') !== 'I_APPROVE_LOCAL_TEST_ONLY'
        || getenv('MARS_VALKEY_TESTS_APPROVED') !== 'I_APPROVE_LOCAL_VALKEY_ONLY') {
        Assert::markTestSkipped('Separate isolated local Valkey approval is required.');
    }

    foreach (['default', 'cache'] as $connection) {
        $settings = config("database.redis.{$connection}");

        if (($settings['host'] ?? '') !== '127.0.0.1') {
            throw new RuntimeException('Valkey host must be strictly 127.0.0.1.');
        }
    }

    if (config('database.redis.options.prefix') !== 'mars:test:') {
        throw new RuntimeException('Valkey test key prefix is not isolated.');
    }
}

it('uses a private namespace and atomic SETNX semantics for duplicate event claims', function (): void {
    marsValkeyTestApproval();

    $connection = Redis::connection('cache');
    $key = 'v4:atomic:'.Str::random(24);

    try {
        expect((int) $connection->setnx($key, 'first'))->toBe(1);
        expect((int) $connection->setnx($key, 'second'))->toBe(0);
        expect($connection->get($key))->toBe('first');

        $connection->expire($key, 30);
        $ttl = (int) $connection->ttl($key);

        expect($ttl)->toBeGreaterThan(0)
            ->toBeLessThanOrEqual(30);
    } finally {
        $connection->del($key);
    }
});
