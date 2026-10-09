<?php

use App\Support\Channels\ChannelPayloadHasher;

it('v2 channels hash semantically identical nested maps equally despite key order', function () {
    $hasher = new ChannelPayloadHasher;
    $one = ['event' => 'created', 'order' => ['id' => 10, 'currency' => 'TRY']];
    $two = ['order' => ['currency' => 'TRY', 'id' => 10], 'event' => 'created'];

    expect($hasher->hash($one))->toBe($hasher->hash($two));
});

it('v2 channels preserve list order when hashing idempotency payloads', function () {
    $hasher = new ChannelPayloadHasher;

    expect($hasher->hash(['ids' => [1, 2, 3]]))
        ->not->toBe($hasher->hash(['ids' => [3, 2, 1]]));
});

it('v2 channels detect changes to an external event payload', function () {
    $hasher = new ChannelPayloadHasher;
    expect($hasher->hash(['amount' => '1.0000']))
        ->not->toBe($hasher->hash(['amount' => '1.0001']));
});

it('v2 channels preserve payload type information', function () {
    $hasher = new ChannelPayloadHasher;
    expect($hasher->hash(['id' => 123]))
        ->not->toBe($hasher->hash(['id' => '123']));
});

it('v2 channels produce deterministic SHA-256 hex output', function () {
    $hasher = new ChannelPayloadHasher;
    $hash = $hasher->hash(['merchant' => 'İstanbul', 'path' => '/v1/items']);

    expect($hash)->toMatch('/^[0-9a-f]{64}$/D')
        ->toBe($hasher->hash(['merchant' => 'İstanbul', 'path' => '/v1/items']));
});

it('v2 channels fail closed on malformed UTF-8 payloads', function () {
    expect(fn () => (new ChannelPayloadHasher)->hash(["bad" => "\xB1"]))
        ->toThrow(JsonException::class);
});
