<?php

it('defines one reusable list heading and one accessible shared action dropdown', function (): void {
    $heading = file_get_contents(base_path('resources/views/components/v65/list-heading.blade.php'));
    $menu = file_get_contents(base_path('resources/views/components/v65/action-menu.blade.php'));
    $legacyDrawer = file_get_contents(base_path('resources/views/components/v65/simple-filter-drawer.blade.php'));

    expect($heading)->toContain('v65-standard-list-heading-controls')
        ->toContain('{{ $title }}')
        ->and($menu)->toContain('data-v65-action-menu')
        ->toContain('data-v65-action-menu-panel')
        ->toContain('aria-label')
        ->and($legacyDrawer)->toContain('v65-simple-filter-drawer')
        ->toContain('v65-simple-filter-fields')
        ->toContain('Seçimler otomatik uygulanır.');
});

it('uses the approved right-aligned filter beside the list title on seven independent lists', function (): void {
    foreach ([
        'sales/document-list',
        'pages/warehouse/transport-slip-list',
        'production/production-order-center',
        'production/recipe-center',
        'production/subcontracting-center',
        'imports/import-center',
        'channels/channel-listing-center',
    ] as $name) {
        $blade = file_get_contents(base_path('resources/views/livewire/'.$name.'.blade.php'));
        expect($blade)->toContain('<x-v65.list-heading')
            ->toContain('<x-v65.filter-drawer')
            ->toContain('</x-v65.list-heading>')
            ->toContain('<x-v65.list-footer')
            ->and(substr_count($blade, '<x-v65.filter-drawer'))->toBe(1 );
    }
});

it('keeps the single common export menu and original Livewire methods', function (): void {
    $list = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $sales = file_get_contents(base_path('resources/views/livewire/sales/document-list.blade.php'));
    $cari = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));
    $report = file_get_contents(base_path('resources/views/livewire/reporting/report-center.blade.php'));

    foreach ([$list, $sales, $cari] as $blade) {
        expect($blade)->toContain('<x-v65.action-menu label="Dışa Aktar">');
    }
    expect($list)->toContain('wire:click="exportCsv"')
        ->toContain('wire:click="exportXlsx"')
        ->and($sales)->toContain('wire:click="exportCsv"')
        ->toContain('wire:click="exportXlsx"')
        ->and($cari)->toContain('wire:click="exportStatementCsv"')
        ->toContain('wire:click="exportStatementXlsx"')
        ->toContain('wire:click="exportContactDocumentsCsv"')
        ->toContain('wire:click="exportContactDocumentsXlsx"')
        ->and($report)->toContain('<x-v65.action-menu label="Dışa Aktar">')
        ->toContain('queueExport');
});

it('does not confuse workflow controls with list filters', function (): void {
    $sync = file_get_contents(base_path('resources/views/livewire/channels/channel-sync-center.blade.php'));
    $print = file_get_contents(base_path('resources/views/livewire/reporting/print-history.blade.php'));
    $quarantine = file_get_contents(base_path('resources/views/livewire/pages/stock/quarantine-control.blade.php'));
    $salesEditor = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));

    foreach ([$sync, $print, $quarantine] as $blade) {
        expect($blade)->toContain('<x-v65.simple-filter-drawer>');
    }
    expect($sync)->toContain('wire:click="pollNow"')
        ->and($quarantine)->toContain('wire:model="decisionNote"')
        ->and($salesEditor)->not->toContain('<x-v65.simple-filter-drawer>');
});

it('standardizes mobile, dropdown and table styles without modifying print templates', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    $js = file_get_contents(base_path('resources/js/app.js'));

    expect($css)->toContain('--v65-list-btn-compact:28px')
        ->toContain('--v65-list-btn-normal:38px')
        ->toContain('.v65-standard-action-menu:not([open])')
        ->toContain('max-width:min(280px,calc(100vw - 24px))')
        ->toContain('.v65-simple-filter-drawer[open]')
        ->toContain('.v65-list-page table.data-table tbody td')
        ->toContain('@media(max-width:680px)')
        ->toContain('@media print')
        ->and($js)->toContain('details[data-v65-action-menu]')
        ->toContain("document.addEventListener('pointerdown'")
        ->toContain("document.addEventListener('keydown'")
        ->toContain("document.addEventListener('livewire:navigating'");
});

it('excludes generic print and back buttons from independently managed list pages', function (): void {
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $js = file_get_contents(base_path('resources/js/app.js'));
    $drawerCss = file_get_contents(base_path('resources/css/v65-filter-drawer.css'));
    $bank = file_get_contents(base_path('resources/views/livewire/finance/bank-statement-center.blade.php'));

    expect($layout)->toContain('$v65IndexRoutes = [')
        ->toContain("'stock.warehouse-slips.index'")
        ->toContain("'channels.sync'")
        ->toContain('in_array($routeName, $v65IndexRoutes, true)')
        ->and($js)->toContain('details.page-action-menu button')
        ->not->toContain('details[data-v65-action-menu] button')
        ->and($drawerCss)->toContain('width:100%;height:38px;min-height:38px;')
        ->and($bank)->toContain('<x-v65.list-heading title="Banka Ekstresi / Mutabakat"')
        ->toContain('class="align-end">Tutar');
});

it('preserves the product image-free listing and record-identifier links', function (): void {
    $table = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $products = file_get_contents(base_path('app/Livewire/Pages/Products/ProductList.php'));
    $contacts = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactList.php'));

    expect($table)->toContain('v65-products-heading-actions')
        ->toContain('v65-cari-list-intro')
        ->toContain('v65-record-link')
        ->and($products)->toContain("['code', 'name']")
        ->and($contacts)->toContain("['code', 'title']")
        ->toContain("function rowActions(): array");
});


it('defines and uses the new reusable V65 form and list primitives', function (): void {
    foreach (['button', 'status-badge', 'select', 'empty-state', 'form-actions'] as $name) {
        expect(is_file(base_path('resources/views/components/v65/'.$name.'.blade.php')))->toBeTrue();
    }

    $button = file_get_contents(base_path('resources/views/components/v65/button.blade.php'));
    $select = file_get_contents(base_path('resources/views/components/v65/select.blade.php'));
    $status = file_get_contents(base_path('resources/views/components/v65/status-badge.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    $accounts = file_get_contents(base_path('resources/views/livewire/finance/finance-accounts.blade.php'));
    $bank = file_get_contents(base_path('resources/views/livewire/finance/bank-statement-center.blade.php'));
    $collection = file_get_contents(base_path('resources/views/livewire/finance/collection-form.blade.php'));
    $unit = file_get_contents(base_path('resources/views/livewire/pages/catalog/unit-form.blade.php'));
    $category = file_get_contents(base_path('resources/views/livewire/pages/catalog/category-list.blade.php'));

    expect($button)->toContain("'xs', 'sm', 'md', 'lg', 'xl'")
        ->toContain("'primary', 'secondary', 'ghost', 'danger', 'success', 'warning'")
        ->toContain('type="{{ $type }}"')
        ->toContain('href="{{ $href }}"')
        ->and($select)->toContain('{{ $slot }}')
        ->toContain('$attributes->except')
        ->and($status)->toContain("'neutral', 'success', 'danger', 'warning', 'info'")
        ->and($css)->toContain('--v65-component-height:28px')
        ->toContain('--v65-component-height:32px')
        ->toContain('--v65-component-height:38px')
        ->toContain('--v65-component-height:44px')
        ->toContain('--v65-component-height:48px')
        ->and($accounts)->toContain('<x-v65.status-badge')
        ->toContain('class="align-end">Defter')
        ->and($bank)->toContain('<x-v65.select label="Banka"')
        ->toContain('wire:click="reconcileExisting"')
        ->toContain('wire:click="createFromStatement"')
        ->and($collection)->toContain('<x-v65.form-actions')
        ->toContain('wire:click="post"')
        ->toContain('wire:click="reverse"')
        ->and($unit)->toContain('<x-v65.empty-state')
        ->and($category)->toContain('<x-v65.status-badge')
        ->toContain('<x-v65.empty-state');
});
