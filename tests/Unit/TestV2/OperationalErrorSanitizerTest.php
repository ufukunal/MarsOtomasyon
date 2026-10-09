<?php

use App\Support\Operations\OperationalErrorSanitizer;

it('v2 operations redact headers and key-value credentials in exception messages', function () {
    $message = (new OperationalErrorSanitizer)->summarize(
        new RuntimeException('POST failed authorization: Bearer token=topsecret password=hunter2 api_key=apikey123'),
    );

    expect($message)->not->toContain('topsecret')
        ->not->toContain('hunter2')
        ->not->toContain('apikey123')
        ->toContain('[REDACTED]');
});

it('v2 operations redact JSON-formatted secrets and URL embedded credentials', function () {
    $output = (new OperationalErrorSanitizer)->summarize(
        '{"password":"password123"} https://service:secret123@example.com/v2',
    );

    expect($output)->not->toContain('password123')
        ->not->toContain('secret123')
        ->toContain('[REDACTED]');
});

it('v2 operations cap unsafe error messages and normalize whitespace', function () {
    $output = (new OperationalErrorSanitizer)->summarize(str_repeat('x', 600)."\n\n");
    expect(mb_strlen($output))->toBe(500);
    expect($output)->not->toContain("\n");
});

it('v2 operations expose an exception class instead of an empty exception message', function () {
    expect((new OperationalErrorSanitizer)->summarize(new RuntimeException('')))
        ->toBe('RuntimeException');
});

it('v2 operations never emit an empty public diagnostic', function () {
    expect((new OperationalErrorSanitizer)->summarize('     '))
        ->toBe('Operational failure.');
});
