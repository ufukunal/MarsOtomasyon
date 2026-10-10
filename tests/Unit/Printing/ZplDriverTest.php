<?php

use App\Enums\PrintType;
use App\Support\Printing\Drivers\ZplDriver;

it('rejects malformed ZPL payloads before reaching a printer', function (): void {
    $driver = new ZplDriver;
    $type = PrintType::cases()[0];
    expect(fn () => $driver->send($type, [], null))->toThrow(InvalidArgumentException::class);
    expect(fn () => $driver->send($type, ['zpl' => 'DANGEROUS'], null))->toThrow(InvalidArgumentException::class);
    expect(fn () => $driver->send($type, ['zpl' => '^XA UNTERMINATED'], null))->toThrow(InvalidArgumentException::class);
});

it('returns a valid ZPL payload as a result rather than directly printing', function (): void {
    $type = PrintType::cases()[0];
    $result = (new ZplDriver)->send($type, ['zpl' => '^XA^FO0,0^FDsafe^FS^XZ'], null);
    expect($result->content)->toBe('^XA^FO0,0^FDsafe^FS^XZ')
        ->and($result->mimeType)->toBe('application/zpl');
});
