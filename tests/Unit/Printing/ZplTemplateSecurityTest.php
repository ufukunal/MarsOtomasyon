<?php

use App\Support\Printing\Label\LabelTemplateRenderer;

it('removes ZPL command introducers from untrusted label text', function (): void {
    $renderer = (new ReflectionClass(LabelTemplateRenderer::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(LabelTemplateRenderer::class, 'zpl');
    $result = $method->invoke($renderer, [
        ['settings' => ['text' => "Hello ^XA ~JS\nNewline"]],
    ]);
    expect($result)->toStartWith('^XA')
        ->toEndWith('^XZ')
        ->toContain('Hello XA JS Newline')
        ->not->toContain('^XA ~JS');
});
