<?php

it('keeps the application skip link keyboard-accessible without changing print layout', function (): void {
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $styles = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($layout)
        ->toContain('<a class="v65-skip-link" href="#main-content">Ana içeriğe geç</a>')
        ->toContain('<main class="content" id="main-content" tabindex="-1">')
        ->and(strpos($layout, 'v65-skip-link'))->toBeLessThan(strpos($layout, '<aside class="sidebar">'))
        ->and($styles)->toContain('.v65-skip-link:focus')
        ->toContain('@media print{.v65-skip-link{display:none}}');
});

it('keeps the legacy product tabs on shared V65 keyboard navigation', function (): void {
    $legacy = file_get_contents(base_path('resources/views/components/tabs.blade.php'));
    $v65 = file_get_contents(base_path('resources/views/components/v65/tabs.blade.php'));
    $product = file_get_contents(base_path('resources/views/livewire/pages/products/product-form.blade.php'));
    $js = file_get_contents(base_path('resources/js/app.js'));

    expect($product)->toContain('<x-tabs :tabs="$editTabs" :active="$activeTab" />')
        ->and($legacy)->toContain('<x-v65.tabs :tabs="$tabs" :active="$active" panel-prefix="v65-product-edit" />')
        ->and($v65)->toContain('data-v65-tabs')
        ->toContain('aria-selected')
        ->toContain('tabindex=')
        ->and($js)->toContain('[data-v65-tabs] [role="tab"]')
        ->toContain("event.key === 'ArrowRight'");
});

it('uses valid label field markup and scrollable contact detail tables', function (): void {
    $textField = file_get_contents(base_path('resources/views/components/field/text.blade.php'));
    $contact = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-form.blade.php'));
    $detail = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));

    expect($textField)->toContain('<span class="field-control">')
        ->toContain('</span>')
        ->not->toContain('<div class="field-control">')
        ->and(substr_count($contact, '<div class="table-scroll v65-list-table"><table class="data-table">'))
        ->toBe(3)
        ->and(substr_count($contact, '<th scope="col">İşlemler</th>'))->toBe(3)
        ->and($contact)->toContain('wire:confirm="Cari adresi silinsin mi?"')
        ->toContain('wire:confirm="Cari yetkili kaydı silinsin mi?"')
        ->toContain('wire:confirm="Cari banka bilgisi silinsin mi?"')
        ->and($detail)->toContain('max(0, min(100, (float) $category->percent))');
});
