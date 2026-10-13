<?php

it('shows relevant product edit tabs and routes without duplicate global actions', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/pages/products/product-form.blade.php'));
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));

    expect($view)->toContain('$editTabs')
        ->toContain('$product->kind->value === \'set\'')
        ->toContain('$product->kind->value === \'configurable\'')
        ->toContain("route('products.index')")
        ->toContain("route('products.show'")
        ->toContain("['general', 'price', 'stock', 'type']")
        ->toContain('v65-product-edit-savebar')
        ->and($layout)->toContain("['products.create', 'products.edit']")
        ->toContain("['products.show', 'products.create', 'products.edit']");
});

it('keeps image operations functional while grouping controls into consistent cards', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/products/product-images.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($view)->toContain('data-product-image-sorter')
        ->toContain('wire:click="upload"')
        ->toContain('wire:click="setCover(')
        ->toContain('wire:click="move(')
        ->toContain('wire:click="delete(')
        ->toContain('v65-product-image-grid')
        ->toContain('v65-product-image-action-row')
        ->toContain('@disabled($loop->first)')
        ->toContain('@disabled($loop->last)')
        ->and($css)->toContain('.v65-product-edit-card')
        ->toContain('.v65-product-image-action-row');
});

it('does not lose numeric precision while trimming redundant zeros', function (): void {
    $php = file_get_contents(base_path('app/Livewire/Pages/Products/ProductForm.php'));
    expect($php)->toContain('private static function displayDecimal')
        ->toContain("str_contains(\$value, '.')")
        ->toContain("return \$formatted === '' ? '0' : \$formatted;");
});
