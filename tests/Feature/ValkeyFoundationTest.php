<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class ValkeyFoundationTest extends TestCase
{
    public function test_valkey_connections_use_separate_databases(): void
    {
        self::assertSame('0', (string) config('database.redis.default.database'));
        self::assertSame('1', (string) config('database.redis.cache.database'));
        self::assertSame('2', (string) config('database.redis.queue.database'));
        self::assertSame('3', (string) config('database.redis.session.database'));
    }

    public function test_all_valkey_connections_are_reachable(): void
    {
        foreach (['default', 'cache', 'queue', 'session'] as $connection) {
            $result = Redis::connection($connection)->command('ping');

            self::assertTrue($result === true || $result === 'PONG' || $result === '+PONG');
        }
    }

    public function test_cache_round_trip_and_lock_work(): void
    {
        Cache::put('foundation:cache-probe', 'ready', 10);
        self::assertSame('ready', Cache::get('foundation:cache-probe'));
        Cache::forget('foundation:cache-probe');
        self::assertNull(Cache::get('foundation:cache-probe'));

        $lock = Cache::lock('foundation:lock-probe', 10);
        self::assertTrue($lock->get());
        $lock->release();
    }

    public function test_queue_is_bound_to_dedicated_valkey_connection_and_after_commit(): void
    {
        self::assertSame('redis', config('queue.default'));
        self::assertSame('queue', config('queue.connections.redis.connection'));
        self::assertTrue(config('queue.connections.redis.after_commit'));
    }

    public function test_session_is_bound_to_dedicated_valkey_connection(): void
    {
        self::assertSame('redis', config('session.driver'));
        self::assertSame('session', config('session.connection'));
    }
}
