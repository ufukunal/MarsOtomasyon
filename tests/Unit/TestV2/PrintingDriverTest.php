<?php

use App\Enums\PrintType;
use App\Support\Printing\Drivers\TextDriver;
use App\Support\Printing\Drivers\ZplDriver;

it('v2 printing produces an exact ZPL payload and default filename', function () {
    $result = (new ZplDriver)->send(PrintType::ProductLabel, ['zpl' => '^XA^FO0,0^FDTest^FS^XZ'], null);

    expect($result->content)->toBe('^XA^FO0,0^FDTest^FS^XZ')
        ->and($result->mimeType)->toBe('application/zpl')
        ->and($result->filename)->toBe('product_label.zpl');
});

it('v2 printing permits explicit label filenames without altering the sent payload', function () {
    $result = (new ZplDriver)->send(PrintType::CartonLabel, [
        'zpl' => '^XA^XZ',
        'filename' => 'carton-10.zpl',
    ], null);

    expect($result->filename)->toBe('carton-10.zpl')
        ->and($result->content)->toBe('^XA^XZ');
});

it('v2 printing rejects malformed ZPL envelope and missing data', function (mixed $payload) {
    expect(fn () => (new ZplDriver)->send(PrintType::ProductLabel, $payload, null))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'missing zpl' => [[]],
    'empty zpl' => [['zpl' => '']],
    'wrong prefix' => [['zpl' => '^FO0,0^XZ']],
    'wrong suffix' => [['zpl' => '^XA^FO0,0']],
    'non-string' => [['zpl' => [1, 2]]],
]);

it('v2 printing supports UTF-8 text output with exact bytes', function () {
    $result = (new TextDriver)->send(PrintType::Receipt, ['text' => "İrsaliye: 123\nİstanbul"], null);

    expect($result->content)->toBe("İrsaliye: 123\nİstanbul")
        ->and($result->mimeType)->toBe('text/plain; charset=UTF-8')
        ->and($result->filename)->toBe('receipt.txt');
});

it('v2 printing distinguishes empty printable text from absent text payload', function () {
    expect((new TextDriver)->send(PrintType::Report, ['text' => ''], null)->content)->toBe('');

    expect(fn () => (new TextDriver)->send(PrintType::Report, [], null))
        ->toThrow(InvalidArgumentException::class);
});
