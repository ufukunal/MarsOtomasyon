<?php

it('keeps exactly one create action and exports in the page header for shared non-product tables', function (): void {
    $markup = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $headerStart = strpos($markup, '@unless($isProductTable)');
    $filtersStart = strpos($markup, '<div @class([\'table-toolbar\'', $headerStart);
    expect($headerStart)->not->toBeFalse()
        ->and($filtersStart)->not->toBeFalse();

    $header = substr($markup, $headerStart, $filtersStart - $headerStart);
    $filterEnd = strpos($markup, '@if(!$isProductTable && $selected !== []', $filtersStart);
    $filters = substr($markup, $filtersStart, $filterEnd - $filtersStart);

    expect($header)->toContain('@teleport(\'.page-actions.v65-page-actions\')')
        ->toContain('v65-data-table-heading-actions')
        ->toContain('table-add-action')
        ->toContain('wire:click="exportCsv"')
        ->toContain('wire:click="exportXlsx"')
        ->and($filters)->toContain('v65-data-table-filter-fields')
        ->not->toContain('table-add-action')
        ->not->toContain('wire:click="exportCsv"')
        ->not->toContain('wire:click="exportXlsx"');
});

it('keeps stock-date filters readable with distinct accessible start/end labels', function (): void {
    $markup = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($markup)->toContain('v65-date-range-filter')
        ->toContain('v65-date-range-input')
        ->toContain('aria-label="{{ $filter->label }} başlangıç"')
        ->toContain('aria-label="{{ $filter->label }} bitiş"')
        ->toContain('v65-data-table-filters')
        ->and($css)->toContain('grid-template-columns:auto minmax(0,1fr) minmax(0,1fr)')
        ->toContain('.v65-data-table-heading-actions')
        ->toContain('max-width:240px');
});
