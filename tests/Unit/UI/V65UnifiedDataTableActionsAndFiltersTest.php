<?php

it('keeps Cari header actions in the approved order while sharing one filter include', function (): void {
    $markup = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $drawer = file_get_contents(base_path('resources/views/livewire/components/partials/v65-table-filter-controls.blade.php'));

    $start = strpos($markup, '<div class="v65-cari-heading-actions">');
    $end = strpos($markup, '</x-v65.action-menu>', $start);
    expect($start)->not->toBeFalse()->and($end)->not->toBeFalse();
    $actions = substr($markup, $start, $end - $start);
    $previous = -1;
    foreach (['+ Yeni Cari', '>Yazdır</button>', 'Dışa Aktar'] as $caption) {
        $position = strpos($actions, $caption);
        expect($position)->not->toBeFalse()->toBeGreaterThan($previous);
        $previous = $position;
    }

    expect($actions)->toContain('<x-v65.action-menu label="Dışa Aktar">')
        ->toContain('wire:click="exportCsv"')
        ->toContain('wire:click="exportXlsx"')
        ->and($markup)->toContain('v65-cari-intro-filters')
        ->toContain("@include('livewire.components.partials.v65-table-filter-controls')")
        ->and($drawer)->toContain('<x-v65.filter-drawer')
        ->toContain('wire:model="v65DraftFilters.search"')
        ->not->toContain('wire:click="exportCsv"')
        ->not->toContain('table-add-action');
});

it('provides distinct date bounds inside the shared drawer', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/components/partials/v65-table-filter-controls.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-filter-drawer.css'));

    expect($view)->toContain('aria-label="{{ $filter->label }} başlangıç"')
        ->toContain('aria-label="{{ $filter->label }} bitiş"')
        ->toContain('v65DraftFilters.filters.{{ $filter->key }}.from')
        ->toContain('v65DraftFilters.filters.{{ $filter->key }}.to')
        ->and($css)->toContain('.v65-filter-date-pair')
        ->toContain('grid-template-columns:repeat(2,minmax(0,1fr))');
});
