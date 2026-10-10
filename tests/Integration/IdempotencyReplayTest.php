<?php

use App\Support\Concurrency\IdempotencyKey;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

it('replays results without repeating a period mutation', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $calls = 0;
        $key = 'v4-'.Str::random(12);
        $make = function () use (&$calls): array {
            $calls++;

            return ['status' => 'created', 'id' => 14];
        };
        expect(IdempotencyKey::run($key, 'sales.invoice.post', $make))->toBe(['status' => 'created', 'id' => 14]);
        expect(IdempotencyKey::run($key, 'sales.invoice.post', $make))->toBe(['status' => 'created', 'id' => 14]);
        expect($calls)->toBe(1);
    });
});

it('rejects reusing an idempotency key for another business action', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $key = 'v4-'.Str::random(12);
        IdempotencyKey::run($key, 'sales.invoice.post', fn () => true);
        expect(fn () => IdempotencyKey::run($key, 'purchase.invoice.post', fn () => true))
            ->toThrow(RuntimeException::class);
    });
});
