<?php

it('uses one compact density contract across list pages without shrinking editors', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($css)->toContain('v65 compact list-density contract')
        ->toContain('.v65-list-page:not(.v65-products) .v65-list-table')
        ->toContain('height:36px')
        ->toContain('height:42px')
        ->toContain('padding:5px 10px')
        ->toContain('min-height:30px')
        ->toContain('.v65-list-row-actions')
        ->toContain('.row-actions')
        ->not->toContain('.document-editor-form .data-table tbody tr{height:42px}');
});

it('continues to use the shared compact table wrapper for brand and unit rows', function (): void {
    $table = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $documents = file_get_contents(base_path('resources/views/livewire/sales/document-list.blade.php'));
    $warehouse = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-list.blade.php'));

    expect($table)->toContain("'v65-list-page'")
        ->toContain('v65-list-table')
        ->toContain('class="row-actions"')
        ->toContain('wire:click="runRowAction(')
        ->and($documents)->toContain('v65-list-page')
        ->toContain('v65-list-row-actions')
        ->and($warehouse)->toContain('v65-list-page')
        ->toContain('v65-list-table');
});
