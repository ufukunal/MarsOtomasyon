<?php

use App\Livewire\Purchases\GoodsReceiptList;
use App\Livewire\Purchases\PurchaseOrderList;
use App\Livewire\Purchases\SupplierInvoiceList;
use App\Livewire\Sales\DispatchList;
use App\Livewire\Sales\ProformaList;
use App\Livewire\Sales\QuoteList;
use App\Livewire\Sales\SalesInvoiceList;
use App\Livewire\Sales\SalesOrderList;

it('uses one footer for shared data tables, sales/purchase lists and warehouse slips', function (): void {
    foreach ([
        'resources/views/livewire/components/data-table.blade.php',
        'resources/views/livewire/sales/document-list.blade.php',
        'resources/views/livewire/pages/warehouse/transport-slip-list.blade.php',
    ] as $path) {
        $markup = file_get_contents(base_path($path));

        expect($markup)
            ->toContain('<x-v65.list-footer')
            ->not->toContain('->links()');
    }

    $component = file_get_contents(base_path('resources/views/components/v65/list-footer.blade.php'));
    expect($component)
        ->toContain('25, 50, 100')
        ->toContain('setPerPage')
        ->toContain('columnControls')
        ->toContain('livewire.components.data-table-pagination');
});

it('keeps isolated, permissioned document queries on both sales and purchase lists', function (): void {
    foreach ([
        QuoteList::class, SalesOrderList::class, DispatchList::class,
        SalesInvoiceList::class, ProformaList::class,
        PurchaseOrderList::class, GoodsReceiptList::class, SupplierInvoiceList::class,
    ] as $class) {
        expect(is_subclass_of($class, \Livewire\Component::class))->toBeTrue();
    }

    $source = file_get_contents(base_path('app/Livewire/Concerns/WithV65DocumentList.php'));
    expect($source)->toContain("where('document_type', \$this->documentType()->value)")
        ->toContain('auth()->user()?->can($this->permission())')
        ->toContain('str_replace(\'.view\', \'.create\', $this->permission())')
        ->toContain('whereDate(')
        ->toContain('whereHas(')
        ->toContain('paginate($this->perPage)')
        ->toContain('resetPage()')
        ->toContain('exportCsv()')
        ->toContain('exportXlsx()');
});

it('enforces v65 list table structure in each converted operation panel', function (string $path): void {
    $markup = file_get_contents(base_path($path));
    expect($markup)->toContain('v65-list-page')
        ->toContain('v65-list-table')
        ->toContain('<table');
})->with([
    'production orders' => 'resources/views/livewire/production/production-order-center.blade.php',
    'production recipes' => 'resources/views/livewire/production/recipe-center.blade.php',
    'subcontracting' => 'resources/views/livewire/production/subcontracting-center.blade.php',
    'import shipments' => 'resources/views/livewire/imports/import-center.blade.php',
    'channel accounts' => 'resources/views/livewire/channels/channel-account-center.blade.php',
    'channel listings' => 'resources/views/livewire/channels/channel-listing-center.blade.php',
    'channel sync' => 'resources/views/livewire/channels/channel-sync-center.blade.php',
    'finance' => 'resources/views/livewire/finance/finance-operation-center.blade.php',
    'bank statements' => 'resources/views/livewire/finance/bank-statement-center.blade.php',
    'securities' => 'resources/views/livewire/finance/securities-center.blade.php',
    'contact aging' => 'resources/views/livewire/finance/contact-aging.blade.php',
    'returns' => 'resources/views/livewire/returns/return-center.blade.php',
    'report center' => 'resources/views/livewire/reporting/report-center.blade.php',
    'print history' => 'resources/views/livewire/reporting/print-history.blade.php',
    'operations' => 'resources/views/livewire/pages/settings/operations-center.blade.php',
    'period carry' => 'resources/views/livewire/pages/settings/period-carry.blade.php',
    'integrity report' => 'resources/views/livewire/pages/settings/integrity-report.blade.php',
    'variant groups' => 'resources/views/livewire/pages/products/variant-group-detail.blade.php',
]);

it('uses real server-side paging for primary production, import and channel event lists', function (string $livewireClass, string $view): void {
    $controller = file_get_contents(base_path($livewireClass));
    $template = file_get_contents(base_path($view));
    expect($controller)->toContain('use WithPagination;')
        ->toContain('paginate($this->perPage)')
        ->toContain('setPerPage')
        ->and($template)->toContain('<x-v65.list-footer');
})->with([
    ['app/Livewire/Production/ProductionOrderCenter.php', 'resources/views/livewire/production/production-order-center.blade.php'],
    ['app/Livewire/Production/RecipeCenter.php', 'resources/views/livewire/production/recipe-center.blade.php'],
    ['app/Livewire/Production/SubcontractingCenter.php', 'resources/views/livewire/production/subcontracting-center.blade.php'],
    ['app/Livewire/Imports/ImportCenter.php', 'resources/views/livewire/imports/import-center.blade.php'],
    ['app/Livewire/Channels/ChannelListingCenter.php', 'resources/views/livewire/channels/channel-listing-center.blade.php'],
    ['app/Livewire/Channels/ChannelSyncCenter.php', 'resources/views/livewire/channels/channel-sync-center.blade.php'],
]);

it('keeps the approved warehouse print template and transactional buttons out of the list refactor', function (): void {
    $warehouse = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-list.blade.php'));
    $code = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipList.php'));

    expect($warehouse)->toContain("route('warehouse.transport.show'")
        ->toContain("route('warehouse.transport.edit'")
        ->toContain("route('warehouse.transport.create'")
        ->and($code)->toContain("can('warehouse_slips.view')")
        ->toContain('TransportSlip::query()');
});
