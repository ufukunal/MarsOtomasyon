<?php

it('connects product and contact tabs with their active panels', function (): void {
    $tabs = file_get_contents(base_path('resources/views/components/v65/tabs.blade.php'));
    $legacy = file_get_contents(base_path('resources/views/components/tabs.blade.php'));
    $products = file_get_contents(base_path('resources/views/livewire/pages/products/product-form.blade.php'));
    $contacts = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-form.blade.php'));

    expect($tabs)->toContain('aria-controls=')
        ->toContain('aria-selected')
        ->toContain('tabindex=')
        ->toContain('panelPrefix')
        ->and($legacy)->toContain('panel-prefix="v65-product-edit"')
        ->and($contacts)->toContain('panel-prefix="v65-contact-edit"');

    foreach (['general', 'price', 'stock', 'type', 'components', 'config', 'images'] as $key) {
        expect($products)->toContain('id="v65-product-edit-panel-'.$key.'"')
            ->toContain('aria-labelledby="v65-product-edit-tab-'.$key.'"');
    }
    foreach (['general', 'terms', 'addresses', 'people', 'banks'] as $key) {
        expect($contacts)->toContain('id="v65-contact-edit-panel-'.$key.'"')
            ->toContain('aria-labelledby="v65-contact-edit-tab-'.$key.'"');
    }
});

it('uses consistent Livewire validation feedback on date and file controls', function (): void {
    foreach (['date', 'file', 'text', 'number', 'select', 'textarea'] as $name) {
        $field = file_get_contents(base_path('resources/views/components/field/'.$name.'.blade.php'));
        expect($field)->toContain("aria-invalid")
            ->toContain("wire:model")
            ->toContain('role="alert"')
            ->toContain('$errors->first($fieldKey)')
            ->toContain('<label class="field">');
    }
});

it('routes application print and back controls through delegated events', function (): void {
    $shell = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $script = file_get_contents(base_path('resources/js/app.js'));

    expect($shell)->toContain('data-v65-shell-print')
        ->toContain('data-v65-shell-transport-print')
        ->toContain('data-v65-shell-back')
        ->toContain('data-fallback-url=')
        ->not->toContain('onclick="window.print()"')
        ->not->toContain('onclick="document.querySelector(')
        ->and($script)->toContain('[data-v65-shell-print]')
        ->toContain('[data-v65-shell-transport-print]')
        ->toContain('[data-v65-shell-back]')
        ->toContain("window.history.back()")
        ->toContain("window.location.assign(fallback)");
});

it('keeps standalone business and transport printing functional without inline onclick handlers', function (): void {
    $shared = file_get_contents(base_path('public/js/mars-print-actions.js'));

    expect($shared)->toContain('[data-mars-print]')
        ->toContain('window.print()')
        ->toContain('dataset.marsAutoprint');

    foreach ([
        'resources/views/printing/business-document.blade.php',
        'resources/views/printing/business-document-slip.blade.php',
        'resources/views/printing/transport-slip.blade.php',
    ] as $path) {
        $html = file_get_contents(base_path($path));
        expect($html)->toContain("asset('js/mars-print-actions.js')")
            ->toContain('data-mars-print')
            ->not->toContain('onclick="window.print()"');
    }

    $transport = file_get_contents(base_path('resources/views/printing/transport-slip.blade.php'));
    expect($transport)->toContain('data-mars-autoprint')
        ->not->toContain("window.addEventListener('load', () => { window.print(); });")
        ->toContain('OpaqueQrSvg::render');
});

it('has read-only responsive scenarios for all twenty approved routes at five widths', function (): void {
    $browser = file_get_contents(base_path('tests/Browser/specs/v65-twenty-screen-readonly.spec.ts'));
    expect($browser)->toContain('[390, 768, 1024, 1440, 1920]')
        ->toContain('MARS_BROWSER_TEST_20_SCREEN_APPROVED')
        ->toContain("const screens = [")
        ->toContain("const response = await page.goto(screen.url)")
        ->not->toContain('page.screenshot({ path:'); // No unapproved baseline replacement.
});

it('uses shared V65 buttons on contact subrecords and confirms destructive set removal', function (): void {
    $contact = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-form.blade.php'));
    $product = file_get_contents(base_path('resources/views/livewire/pages/products/product-form.blade.php'));

    foreach (['Address', 'Person', 'Bank'] as $entity) {
        expect($contact)->toContain('wire:click="save'.$entity.'"')
            ->toContain('wire:click="edit'.$entity.'(')
            ->toContain('wire:click="delete'.$entity.'(');
    }
    expect(substr_count($contact, '<x-v65.button'))->toBeGreaterThanOrEqual(10)
        ->and($product)->toContain('wire:confirm="Set bileşeni kaldırılacak.')
        ->toContain('class="table-scroll v65-list-table"')
        ->toContain('aria-label="Set bileşen miktarı"');
});

it('makes report selection and asynchronous result announcements accessible', function (): void {
    $center = file_get_contents(base_path('resources/views/livewire/reporting/report-center.blade.php'));

    expect($center)->toContain('aria-pressed=')
        ->toContain('aria-live="polite"')
        ->toContain('<div class="alert" role="status">')
        ->toContain('<div class="alert alert-warning" role="alert">');
});
