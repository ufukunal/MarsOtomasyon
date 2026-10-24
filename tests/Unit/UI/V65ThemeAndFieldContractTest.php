<?php

it('keeps the approved light theme as the default and includes opt-in appearance variants', function (): void {
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $styles = file_get_contents(base_path('resources/css/v65-themes.css'));
    $script = file_get_contents(base_path('resources/js/app.js'));

    expect($layout)
        ->toContain('data-mars-theme="light"')
        ->toContain("'resources/css/v65-themes.css'")
        ->toContain('data-mars-theme-select')
        ->toContain('<option value="light">Açık</option>')
        ->toContain('<option value="dim">Orta</option>')
        ->toContain('<option value="dark">Koyu</option>')
        ->and($styles)->toContain(':root[data-mars-theme="dim"]')
        ->toContain(':root[data-mars-theme="dark"]')
        ->toContain('@media print')
        ->and($script)->toContain('mars.ui.theme')
        ->toContain('document.documentElement.dataset.marsTheme')
        ->toContain("document.addEventListener('livewire:navigated'");
});

it('preserves shared field labels and Livewire bindings with accessible validation states', function (): void {
    foreach (['text', 'select', 'textarea', 'number'] as $name) {
        $blade = file_get_contents(base_path('resources/views/components/field/'.$name.'.blade.php'));

        expect($blade)->toContain('<label class="field">')
            ->toContain("'wire:model'")
            ->toContain("'wire:model.live'")
            ->toContain("'wire:model.blur'")
            ->toContain('aria-invalid')
            ->toContain('role="alert"')
            ->toContain('$errors->first($fieldKey)');
    }

    $text = file_get_contents(base_path('resources/views/components/field/text.blade.php'));
    expect($text)->toContain('<span class="field-control">')
        ->not->toContain('<div class="field-control">');

    $product = file_get_contents(base_path('resources/views/livewire/pages/products/product-form.blade.php'));
    $contact = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-form.blade.php'));
    expect($product)->toContain('<x-field.text label="Ad" wire:model="name"')
        ->and($contact)->toContain('<x-field.text label="Unvan" wire:model="title"');
});
