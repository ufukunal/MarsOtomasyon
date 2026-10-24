<?php

it('ships the six shared V65 advanced components', function (): void {
    foreach (['modal', 'search-field', 'multi-select', 'bulk-actions', 'tabs', 'notice'] as $name) {
        expect(is_file(base_path('resources/views/components/v65/'.$name.'.blade.php')))->toBeTrue();
    }

    $modal = file_get_contents(base_path('resources/views/components/v65/modal.blade.php'));
    $search = file_get_contents(base_path('resources/views/components/v65/search-field.blade.php'));
    $multiselect = file_get_contents(base_path('resources/views/components/v65/multi-select.blade.php'));
    $bulk = file_get_contents(base_path('resources/views/components/v65/bulk-actions.blade.php'));
    $tabs = file_get_contents(base_path('resources/views/components/v65/tabs.blade.php'));
    $notice = file_get_contents(base_path('resources/views/components/v65/notice.blade.php'));

    expect($modal)->toContain('<dialog')
        ->toContain('aria-labelledby')
        ->toContain('wire:ignore.self')
        ->toContain('data-v65-modal-close')
        ->and($search)->toContain('type="search"')
        ->toContain('$attributes->except')
        ->and($multiselect)->toContain('<fieldset')
        ->toContain('{{ $slot }}')
        ->and($bulk)->toContain('if((int) $count > 0)')
        ->toContain('v65-bulk-actions')
        ->and($tabs)->toContain('role="tablist"')
        ->toContain('aria-selected')
        ->toContain('aria-controls')
        ->toContain('panelPrefix')
        ->toContain('wire:click')
        ->and($notice)->toContain('v65-notice--')
        ->toContain("['warning', 'danger']");
});

it('retains the original Livewire bindings when advanced components are applied', function (): void {
    $contacts = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-form.blade.php'));
    $securities = file_get_contents(base_path('resources/views/livewire/finance/securities-center.blade.php'));
    $channel = file_get_contents(base_path('resources/views/livewire/channels/channel-account-center.blade.php'));
    $filters = file_get_contents(base_path('resources/views/livewire/components/partials/v65-table-filter-controls.blade.php'));

    expect($contacts)->toContain('<x-v65.tabs')
        ->toContain(':active="$activeTab"')
        ->toContain('<x-v65.multi-select')
        ->toContain('wire:model="categoryIds"')
        ->toContain('type="submit"')
        ->and($securities)->toContain('<x-v65.bulk-actions')
        ->toContain('wire:model="selectedSecurityIds"')
        ->toContain('data-v65-modal-open="v65-security-reversal-confirm"')
        ->toContain('wire:click="reversePayroll"')
        ->toContain('wire:click="postPayroll"')
        ->toContain('wire:click="create"')
        ->and(substr_count($securities, 'wire:click="reversePayroll"'))->toBe(1)
        ->and($channel)->toContain('<x-v65.notice tone="success"')
        ->toContain('<x-v65.notice tone="warning"')
        ->toContain('<x-v65.status-badge')
        ->and($filters)->toContain('<x-v65.search-field')
        ->toContain('wire:model="v65DraftFilters.search"');
});

it('keeps modal and tabs keyboard handling scoped and printing unaffected', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    $js = file_get_contents(base_path('resources/js/app.js'));

    expect($css)->toContain('.app-shell .v65-modal')
        ->toContain('.app-shell .v65-modal::backdrop')
        ->toContain('.app-shell .v65-multi-select-options')
        ->toContain('.app-shell .v65-tabs')
        ->toContain('.app-shell .v65-notice')
        ->toContain('@media print')
        ->and($js)->toContain('[data-v65-modal-open]')
        ->toContain('[data-v65-modal-close]')
        ->toContain('dialog.showModal()')
        ->toContain('v65ModalFocus')
        ->toContain('[data-v65-tabs] [role="tab"]')
        ->toContain("event.key === 'ArrowRight'");
});
