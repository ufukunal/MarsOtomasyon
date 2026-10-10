<?php

use App\Enums\PrintType;
use App\Support\Printing\Drivers\BrowserDriver;
use App\Support\Printing\Drivers\TextDriver;
use App\Support\Printing\Label\CartonLabelData;

it('renders UTF-8 text jobs without contacting printers or opening browser processes', function (): void {
    $print = (new TextDriver)->send(PrintType::cases()[0], [
        'text' => 'İrsaliye: TR-001',
        'filename' => 'delivery.txt',
    ], null);

    expect($print->content)->toBe('İrsaliye: TR-001')
        ->and($print->mimeType)->toBe('text/plain; charset=UTF-8')
        ->and($print->filename)->toBe('delivery.txt');
});

it('rejects missing print payloads before invoking an external renderer', function (): void {
    $type = PrintType::cases()[0];

    expect(fn () => (new TextDriver)->send($type, [], null))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => (new BrowserDriver)->send($type, ['html' => ''], null))
        ->toThrow(InvalidArgumentException::class);
});

it('requires a real shipping source and document snapshot for carton labels', function (): void {
    expect(fn () => new CartonLabelData('', 1, ['number' => '1']))->toThrow(DomainException::class);
    expect(fn () => new CartonLabelData('dispatch', 0, ['number' => '1']))->toThrow(DomainException::class);
    expect(fn () => new CartonLabelData('dispatch', 1, []))->toThrow(DomainException::class);
});

it('maps shipping label records into immutable approved renderer data', function (): void {
    $label = new CartonLabelData('dispatch', 17, ['number' => 'D-17'], ['city' => 'İstanbul']);
    $data = $label->toRenderData();

    expect($data->value('document.number'))->toBe('D-17')
        ->and($data->value('shipping.city'))->toBe('İstanbul');
});
