<?php

it('keeps the four Cari Detay menus native, permission-guarded and separate from detail tabs', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));

    expect(substr_count($view, '<details class="v65-contact-action-menu">'))->toBe(4)
        ->and($view)->toContain('Alım İşlemleri')
        ->toContain('Satış İşlemleri')
        ->toContain('İade İşlemleri')
        ->toContain('<summary>Diğer</summary>')
        ->toContain("@can('contacts.update')")
        ->toContain("@can('returns.view')")
        ->toContain('v65-contact-tabs');
});

it('never displays the panel of a closed Cari Detay menu even when author CSS sets display grid', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($css)
        ->toContain('.v65-contact-action-menu:not([open])>.v65-contact-action-panel{display:none!important}')
        ->toContain('.v65-contact-action-menu[open]{z-index:75}')
        ->toContain('.page-header:has(.v65-contact-top-actions){position:relative;z-index:30;overflow:visible}')
        ->toContain('.v65-contact-action-menu summary:focus-visible');
});

it('closes other open menus on pointer, native keyboard toggle, Escape and Livewire navigation', function (): void {
    $js = file_get_contents(base_path('resources/js/app.js'));

    expect($js)->toContain('details.v65-contact-action-menu')
        ->toContain('closeV65ActionMenus(current)')
        ->toContain("document.addEventListener('pointerdown'")
        ->toContain("document.addEventListener('toggle'")
        ->toContain("document.addEventListener('keydown'")
        ->toContain("event.key !== 'Escape'")
        ->toContain("document.addEventListener('livewire:navigating'")
        ->toContain('opened.querySelector(\'summary\')?.focus()')
        ->not->toContain("if (menu && !menu.matches(");
});
