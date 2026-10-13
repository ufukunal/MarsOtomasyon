<?php

it('opens all shared list column controls in an accessible modal instead of details dropdown', function (): void {
    $dialog = file_get_contents(base_path('resources/views/components/v65/column-picker.blade.php'));
    $table = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $documents = file_get_contents(base_path('resources/views/livewire/sales/document-list.blade.php'));
    $script = file_get_contents(base_path('resources/js/app.js'));
    $styles = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($dialog)->toContain('<dialog')
        ->toContain('wire:ignore.self')
        ->toContain('aria-haspopup="dialog"')
        ->toContain('data-v65-column-picker-open')
        ->toContain('data-v65-column-picker-close')
        ->toContain('data-v65-column-picker-dialog')
        ->toContain('{{ $slot }}')
        ->and($table)->toContain('<x-v65.column-picker>')
        ->not->toContain('<details class="column-picker">')
        ->toContain('wire:click="toggleColumn(')
        ->and($documents)->toContain('<x-v65.column-picker>')
        ->not->toContain('<details class="column-picker">')
        ->toContain('wire:click="toggleColumn(')
        ->and($script)->toContain('dialog.showModal()')
        ->toContain('data-v65-column-picker-open')
        ->toContain('dialog.close()')
        ->and($styles)->toContain('.v65-column-picker-dialog::backdrop')
        ->toContain('.v65-column-picker-dialog[open]')
        ->toContain('.v65-column-picker-option');
});
