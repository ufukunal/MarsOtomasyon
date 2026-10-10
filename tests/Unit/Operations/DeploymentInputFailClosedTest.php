<?php

use App\Support\Operations\DeploymentService;

it('refuses malformed deploy release identifiers before any backup or activation', function (string $release, string $commit): void {
    $activated = false;

    expect(fn () => app(DeploymentService::class)->deploy(
        $release,
        $commit,
        null,
        function () use (&$activated): void {
            $activated = true;
        },
    ))->toThrow(RuntimeException::class);

    expect($activated)->toBeFalse();
})->with([
    'empty release' => ['', 'abcdef0123456789'],
    'path traversal' => ['../../etc', 'abcdef0123456789'],
    'absolute path' => ['/var/www/release', 'abcdef0123456789'],
    'shell separator' => ['v4;rm', 'abcdef0123456789'],
    'invalid commit' => ['v4-safe', 'not-hex'],
    'short commit' => ['v4-safe', 'abcd'],
]);
