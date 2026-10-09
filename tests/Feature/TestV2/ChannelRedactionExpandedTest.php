<?php

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelSensitiveDataRedactor;

it('v2 channel redactor strips nested integration secrets from plain error text', function () {
    $account = new SalesChannelAccount([
        'credentials_encrypted' => [
            'nested' => ['access' => ['secret' => 'S3cr3t.token.7654']],
        ],
    ]);
    $text = (new ChannelSensitiveDataRedactor)->redact('vendor error S3cr3t.token.7654', $account);
    expect($text)->not->toContain('S3cr3t.token.7654')->toContain('[REDACTED]');
});

it('v2 channel redactor safely truncates oversized response summaries', function () {
    $account = new SalesChannelAccount(['credentials_encrypted' => []]);
    $text = (new ChannelSensitiveDataRedactor)->redact(str_repeat('ü', 3000), $account);
    expect(mb_strlen($text))->toBe(2000);
});

it('v2 channel redactor masks api keys and passwords in common error formats', function () {
    $account = new SalesChannelAccount(['credentials_encrypted' => []]);
    $text = (new ChannelSensitiveDataRedactor)->redact('api_key=example-token ***', $account);
    expect($text)->not->toContain('example-token')
        ->not->toContain('example-password')
        ->toContain('[REDACTED]');
});
