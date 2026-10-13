<?php

it('uses one common filter drawer with two tabs and a fixed action footer', function (): void {
    $drawer = file_get_contents(base_path('resources/views/components/v65/filter-drawer.blade.php'));
    $field = file_get_contents(base_path('resources/views/components/v65/filter-field.blade.php'));
    $chips = file_get_contents(base_path('resources/views/components/v65/active-filters.blade.php'));
    $table = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));

    expect($drawer)->toContain('data-v65-filter-dialog')
        ->toContain('data-v65-filter-open')
        ->toContain('v65SetFilterTab')
        ->toContain('Filtreleme')
        ->toContain('Sıralama')
        ->toContain('clearV65Filters')
        ->toContain('applyV65Filters')
        ->and($field)->toContain('v65-filter-field-label')
        ->and($chips)->toContain('removeV65Filter')
        ->and($table)->toContain('<x-v65.filter-drawer')
        ->toContain('<x-v65.active-filters')
        ->not->toContain('v65-products-filter-popover')
        ->not->toContain('<div @class([\'table-toolbar\'');
});

it('preserves record links, list controls and image-free product table', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $product = file_get_contents(base_path('app/Livewire/Pages/Products/ProductList.php'));

    expect($view)->toContain('$this->columnUrl($row, $column)')
        ->toContain('v65-products-name-link')
        ->toContain('<x-v65.list-footer')
        ->not->toContain('v65-product-photo-thumb')
        ->and($product)->toContain("TextFilter::make('code'")
        ->toContain("TextFilter::make('barcode'");
});
