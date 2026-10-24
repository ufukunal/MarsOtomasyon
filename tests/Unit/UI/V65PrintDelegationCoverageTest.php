<?php

it('prints lists and the contact detail through the existing shared shell event', function (): void {
    foreach ([
        'resources/views/livewire/sales/document-list.blade.php',
        'resources/views/livewire/components/data-table.blade.php',
        'resources/views/livewire/pages/contacts/contact-detail.blade.php',
    ] as $path) {
        $source = file_get_contents(base_path($path));
        expect($source)->toContain('data-v65-shell-print')
            ->not->toContain('onclick="window.print()"');
    }

    $script = file_get_contents(base_path('resources/js/app.js'));
    expect($script)->toContain("[data-v65-shell-print]")
        ->toContain('window.print()');
});

it('keeps warehouse preview print tied only to its selected frame', function (): void {
    $view = file_get_contents(base_path(
        'resources/views/livewire/pages/warehouse/transport-slip-editor.blade.php'
    ));
    $script = file_get_contents(base_path('resources/js/app.js'));

    expect($view)->toContain('data-wsl-preview-print')
        ->toContain('data-wsl-preview-frame')
        ->toContain('data-wsl-preview-autoprint')
        ->not->toContain('onclick="document.getElementById(')
        ->not->toContain('onload="this.contentWindow.print()"')
        ->and($script)->toContain("[data-wsl-preview-print]")
        ->toContain("[data-wsl-preview-frame][data-wsl-preview-autoprint]")
        ->toContain("trigger.closest('.wsl-modal')")
        ->toContain('frame.contentWindow?.print()');
});
