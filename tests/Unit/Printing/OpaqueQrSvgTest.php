<?php

use App\Support\Printing\Label\OpaqueQrSvg;

it('creates a stable opaque 32-character identifier distinct for each carton and document', function (): void {
    $key = 'test-signing-secret';
    $first = OpaqueQrSvg::token('company:period:42', 1, $key);

    expect($first)->toMatch('/^[A-F0-9]{32}$/')
        ->toBe(OpaqueQrSvg::token('company:period:42', 1, $key))
        ->not->toBe(OpaqueQrSvg::token('company:period:42', 2, $key))
        ->not->toBe(OpaqueQrSvg::token('company:period:43', 1, $key))
        ->not->toContain('company')
        ->not->toContain('period');
});

it('renders self-contained printable QR markup with a quiet zone but no human-readable token', function (): void {
    $token = OpaqueQrSvg::token('company:period:42', 3, 'test-secret');
    $svg = (string) OpaqueQrSvg::render($token);

    expect($svg)->toContain('<svg')
        ->toContain('viewBox="0 0 37 37"')
        ->toContain('shape-rendering="crispEdges"')
        ->toContain('<path fill="#111" d="M')
        ->not->toContain('<text')
        ->not->toContain($token);
});

it('rejects invalid token payloads or missing signing keys', function (): void {
    expect(fn () => OpaqueQrSvg::render('AF-2026-00001'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => OpaqueQrSvg::token('doc', 1, ''))->toThrow(InvalidArgumentException::class);
});
