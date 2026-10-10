<?php

use App\Support\DocumentTemplates\TemplateDefinitionValidator;

it('rejects unknown top-level fields and an incorrectly shaped sections collection', function (array $definition): void {
    expect(fn () => (new TemplateDefinitionValidator)->normalize($definition))
        ->toThrow(DomainException::class);
})->with([
    ['sections' => [], 'php' => 'echo 1'],
    ['sections' => 'not-an-array'],
    ['sections' => ['header' => ['type' => 'header']]],
    ['sections' => [['type' => 'execute_shell']]],
    ['sections' => [['type' => 'header', 'visible' => 'yes']]],
    ['sections' => [['type' => 'header', 'settings' => ['object' => new stdClass]]]],
]);

it('refuses overlong template definitions before rendering or printing', function (): void {
    $sections = array_fill(0, 101, ['type' => 'header', 'visible' => true, 'settings' => ['title' => 'Safe']]);
    expect(fn () => (new TemplateDefinitionValidator)->normalize(['sections' => $sections]))
        ->toThrow(DomainException::class);
});
