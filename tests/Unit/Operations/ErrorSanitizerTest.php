<?php

use App\Support\Operations\OperationalErrorSanitizer;

it('redacts plain and JSON credential values and URL passwords', function (): void {
    $sanitizer = new OperationalErrorSanitizer;
    $message = $sanitizer->summarize('password=longsecret token:anothersecret "api_key":"veryprivate" https://alice:secret123@example.com/x');
    expect($message)->not->toContain('longsecret')
        ->not->toContain('anothersecret')
        ->not->toContain('veryprivate')
        ->not->toContain('secret123')
        ->toContain('[REDACTED]');
});

it('bounds operational messages and handles blank exceptions', function (): void {
    $sanitizer = new OperationalErrorSanitizer;
    expect(mb_strlen($sanitizer->summarize(str_repeat('x', 999))))->toBe(500)
        ->and($sanitizer->summarize('   '))->toBe('Operational failure.')
        ->and($sanitizer->summarize(new RuntimeException('   ')))->toBe('RuntimeException');
});
