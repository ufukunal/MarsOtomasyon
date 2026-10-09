<?php

use App\Support\Channels\ChannelPayloadHasher;

it('v2 channel hash canonicalizes deeply nested maps without losing list order', function () {
    $hash = new ChannelPayloadHasher;
    expect($hash->hash(['pages' => [['a' => 1, 'b' => 2], ['c' => 3]]]))
        ->toBe($hash->hash(['pages' => [['b' => 2, 'a' => 1], ['c' => 3]]]));
    expect($hash->hash(['pages' => [['a' => 1], ['a' => 2]]]))
        ->not->toBe($hash->hash(['pages' => [['a' => 2], ['a' => 1]]]));
});

it('v2 channel hash distinguishes null from absent values and boolean from numeric', function () {
    $hash = new ChannelPayloadHasher;
    expect($hash->hash(['optional' => null]))->not->toBe($hash->hash([]))
        ->and($hash->hash(['status' => true]))->not->toBe($hash->hash(['status' => 1]));
});

it('v2 channel hash protects exact stringified decimal values', function () {
    $hash = new ChannelPayloadHasher;
    expect($hash->hash(['amount' => '10.0000']))
        ->not->toBe($hash->hash(['amount' => '10.0001']));
});
