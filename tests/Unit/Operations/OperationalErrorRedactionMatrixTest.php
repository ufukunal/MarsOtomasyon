<?php

use App\Support\Operations\OperationalErrorSanitizer;

it('redacts known credential and authorization forms from operational exception text', function (string $input, string $secret): void {
    $output = (new OperationalErrorSanitizer)->summarize(new RuntimeException($input));

    expect($output)->not->toContain($secret)
        ->and($output)->toContain('[REDACTED]')
        ->and($output)->not->toContain("\n");
})->with([
    'password' => ['password=local-example-value', 'local-example-value'],
    'api key' => ['api_key: fake-trendyol-secret', 'fake-trendyol-secret'],
    'secret' => ['consumer_secret=temporary-wc-secret', 'temporary-wc-secret'],
    'authorization' => ['Authorization: Bearer-v4test', 'Bearer-v4test'],
    'embedded HTTP credentials' => ['https://admin:passphrase123@example.test/health', 'passphrase123'],
    'JSON credential' => ['{"token":"sample-token-not-real"}', 'sample-token-not-real'],
]);

it('keeps the operational error summary bounded and returns safe defaults for empty inputs', function (): void {
    $sanitizer = new OperationalErrorSanitizer;

    expect(mb_strlen($sanitizer->summarize(str_repeat('X', 900))))->toBeLessThanOrEqual(500)
        ->and($sanitizer->summarize('   '))->toBe('Operational failure.')
        ->and($sanitizer->summarize(new RuntimeException('')))->toBe('RuntimeException');
});

it('keeps a harmless error category but removes multiline whitespace', function (): void {
    $safe = (new OperationalErrorSanitizer)->summarize("Local database\n connection\t unavailable");

    expect($safe)->toBe('Local database connection unavailable');
});
