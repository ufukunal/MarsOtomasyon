<?php

it('offers a responsive keyboard-accessible native modal drawer', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-filter-drawer.css'));
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $js = file_get_contents(base_path('resources/js/app.js'));

    expect($layout)->toContain('resources/css/v65-filter-drawer.css')
        ->and($css)->toContain('width:min(400px,100vw)')
        ->toContain('height:100dvh')
        ->toContain('overflow-y:auto')
        ->toContain('safe-area-inset-bottom')
        ->toContain('prefers-reduced-motion')
        ->and($js)->toContain('dialog.showModal()')
        ->toContain("document.addEventListener('cancel'")
        ->toContain("document.addEventListener('close'")
        ->toContain('trigger.focus({ preventScroll: true })');
});

it('removes horizontal filter wrappers from every formerly affected list', function (): void {
    $views = [
        'resources/views/livewire/components/data-table.blade.php',
        'resources/views/livewire/sales/document-list.blade.php',
        'resources/views/livewire/pages/warehouse/transport-slip-list.blade.php',
        'resources/views/livewire/production/production-order-center.blade.php',
        'resources/views/livewire/production/recipe-center.blade.php',
        'resources/views/livewire/production/subcontracting-center.blade.php',
        'resources/views/livewire/imports/import-center.blade.php',
        'resources/views/livewire/channels/channel-listing-center.blade.php',
    ];

    foreach ($views as $path) {
        $source = file_get_contents(base_path($path));
        expect($source)->toContain('<x-v65.filter-drawer')
            ->not->toContain('<x-v65.list-filters>');
    }
});
