<?php

it('keeps create and export controls separate from the shared drawer', function (): void {
    $markup = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $header = substr($markup, 0, strpos($markup, '<div class="v65-filter-toolbar">'));
    $drawer = substr($markup, strpos($markup, '<div class="v65-filter-toolbar">'), strpos($markup, '@if(!$isProductTable && $selected !== []') - strpos($markup, '<div class="v65-filter-toolbar">'));

    expect($header)->toContain('v65-data-table-heading-actions')
        ->toContain('table-add-action')
        ->toContain('wire:click="exportCsv"')
        ->toContain('wire:click="exportXlsx"')
        ->and($drawer)->toContain('<x-v65.filter-drawer')
        ->toContain('wire:model="v65DraftFilters.search"')
        ->not->toContain('table-add-action')
        ->not->toContain('wire:click="exportCsv"')
        ->not->toContain('wire:click="exportXlsx"');
});

it('provides distinct date bounds inside the shared drawer', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-filter-drawer.css'));

    expect($view)->toContain('aria-label="{{ $filter->label }} başlangıç"')
        ->toContain('aria-label="{{ $filter->label }} bitiş"')
        ->toContain('v65DraftFilters.filters.{{ $filter->key }}.from')
        ->toContain('v65DraftFilters.filters.{{ $filter->key }}.to')
        ->and($css)->toContain('.v65-filter-date-pair')
        ->toContain('grid-template-columns:repeat(2,minmax(0,1fr))');
});
