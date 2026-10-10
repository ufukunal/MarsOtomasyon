<?php

use App\Support\Channels\ChannelPayloadHasher;

it('uses canonical recursive object-key order for marketplace event identity', function (): void {
    $hash = new ChannelPayloadHasher;
    $a = ['order' => ['id' => 11, 'customer' => ['name' => 'Ürün', 'id' => 4]], 'lines' => [['sku' => 'A', 'qty' => 2]]];
    $b = ['lines' => [['qty' => 2, 'sku' => 'A']], 'order' => ['customer' => ['id' => 4, 'name' => 'Ürün'], 'id' => 11]];

    expect($hash->hash($a))->toBe($hash->hash($b));
    expect(strlen($hash->hash($a)))->toBe(64);
});

it('does not conflate line ordering or amounts across different channel payloads', function (): void {
    $hash = new ChannelPayloadHasher;
    $a = ['lines' => [['sku' => 'A', 'qty' => 1], ['sku' => 'B', 'qty' => 2]]];
    $b = ['lines' => [['sku' => 'B', 'qty' => 2], ['sku' => 'A', 'qty' => 1]]];
    $c = ['lines' => [['sku' => 'A', 'qty' => 1], ['sku' => 'B', 'qty' => 3]]];

    expect($hash->hash($a))->not->toBe($hash->hash($b))
        ->not->toBe($hash->hash($c));
});

it('refuses to hash invalid JSON floating point values', function (): void {
    expect(fn () => (new ChannelPayloadHasher)->hash(['cost' => NAN]))
        ->toThrow(JsonException::class);
});
