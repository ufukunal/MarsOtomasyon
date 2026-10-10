<?php

use App\Support\Channels\ChannelPayloadHasher;

it('is stable for reordered associative payload keys including nested keys', function (): void {
    $hasher = new ChannelPayloadHasher;
    $left = ['order' => ['id' => 3, 'total' => '20'], 'channel' => 'n11'];
    $right = ['channel' => 'n11', 'order' => ['total' => '20', 'id' => 3]];
    expect($hasher->hash($left))->toBe($hasher->hash($right))
        ->and(strlen($hasher->hash($left)))->toBe(64);
});

it('distinguishes list order and different events', function (): void {
    $hasher = new ChannelPayloadHasher;
    expect($hasher->hash(['lines' => [1, 2]]))->not->toBe($hasher->hash(['lines' => [2, 1]]))
        ->and($hasher->hash(['status' => 'created']))->not->toBe($hasher->hash(['status' => 'cancelled']));
});
