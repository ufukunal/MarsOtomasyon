<?php

it('renders shared table pagination without Tailwind-dependent Livewire controls', function (): void {
    $table = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $pagination = file_get_contents(base_path('resources/views/livewire/components/data-table-pagination.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect(substr_count($table, "\$rows->links('livewire.components.data-table-pagination')"))->toBe(2)
        ->and($table)->not->toContain('\$rows->links()')
        ->and($pagination)->toContain('wire:click="previousPage(')
        ->toContain('wire:click="gotoPage(')
        ->toContain('wire:click="nextPage(')
        ->toContain('Önceki')->toContain('Sonraki')
        ->toContain('aria-current="page"')
        ->not->toContain('pagination.previous')->not->toContain('pagination.next')
        ->and($css)->toContain('.mars-pagination-controls')
        ->toContain('.mars-page-button')
        ->toContain('height:32px');
});
