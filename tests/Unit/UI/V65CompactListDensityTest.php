<?php

it('uses a single application-wide compact table density contract', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($css)->toContain('MarsProject v65 application-wide compact table density')
        ->toContain('.app-shell .content table.data-table:not(.wsl-items)')
        ->toContain('height:36px')
        ->toContain('height:42px')
        ->toContain('padding:5px 10px')
        ->toContain('min-height:30px')
        ->toContain('tbody tr:has(:is(input:not([type="checkbox"]),select,textarea,img))')
        ->toContain('.app-shell .content table:not(.data-table)')
        ->toContain('.app-shell .content .v65-products table.data-table')
        ->not->toContain('v65 compact list-density contract');
});

it('keeps all existing table types under the shared rules while leaving PDF/print views outside', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    $paths = [
        'resources/views/livewire/components/data-table.blade.php',
        'resources/views/livewire/sales/document-list.blade.php',
        'resources/views/livewire/sales/document-editor.blade.php',
        'resources/views/livewire/purchases/document-editor.blade.php',
        'resources/views/livewire/pages/products/product-detail.blade.php',
        'resources/views/livewire/pages/contacts/contact-form.blade.php',
        'resources/views/livewire/finance/finance-operation-center.blade.php',
        'resources/views/livewire/imports/import-center.blade.php',
        'resources/views/livewire/production/production-order-center.blade.php',
        'resources/views/livewire/returns/return-center.blade.php',
        'resources/views/livewire/pages/settings/operations-center.blade.php',
        'resources/views/livewire/reporting/report-center.blade.php',
    ];

    foreach ($paths as $path) {
        $markup = file_get_contents(base_path($path));
        expect($markup)->toContain('<table');
    }

    expect($css)->toContain('.app-shell .content')
        ->toContain('table.data-table:not(.wsl-items)')
        ->toContain('table:not(.data-table)')
        ->toContain('.v65-products table.data-table')
        ->not->toContain('.printing .details');

    expect(file_get_contents(base_path('resources/views/printing/transport-slip.blade.php')))
        ->toContain('<table class="details">');
});
