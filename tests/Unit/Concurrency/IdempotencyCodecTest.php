<?php

use App\Support\Concurrency\IdempotencyKey;

it('serializes and decodes nested idempotent business results deterministically', function (): void {
    $codec = new ReflectionMethod(IdempotencyKey::class, 'encodeResult');
    $decoder = new ReflectionMethod(IdempotencyKey::class, 'decodeResult');
    $result = [
        'id' => 42,
        'lines' => [['quantity' => '3.250', 'unit_price' => '10.0000']],
        'status' => 'posted',
        'flags' => [true, false, null],
    ];

    $encoded = $codec->invoke(null, $result);

    expect($encoded)->toBeString();
    expect($decoder->invoke(null, $encoded))->toBe($result);
});

it('never silently persists a non-JSON-serializable idempotency response', function (): void {
    $codec = new ReflectionMethod(IdempotencyKey::class, 'encodeResult');

    expect(fn () => $codec->invoke(null, NAN))->toThrow(RuntimeException::class);
});

it('refuses malformed stored JSON rather than replaying invalid results', function (): void {
    $decoder = new ReflectionMethod(IdempotencyKey::class, 'decodeResult');

    expect(fn () => $decoder->invoke(null, '{not-valid-json'))->toThrow(RuntimeException::class);
});
