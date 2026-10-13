<?php

it('replaces shared list navigation buttons with links on code, name or number', function (): void {
    $classes = [
        'app/Livewire/Pages/Contacts/ContactList.php',
        'app/Livewire/Pages/Products/ProductList.php',
        'app/Livewire/Pages/Pricing/PriceListList.php',
        'app/Livewire/Pages/Catalog/BrandList.php',
        'app/Livewire/Pages/Catalog/UnitList.php',
        'app/Livewire/Pages/Catalog/LocationList.php',
        'app/Livewire/Pages/Stock/TransferList.php',
        'app/Livewire/Pages/Stock/StockCountList.php',
        'app/Livewire/Pages/Stock/WarehouseSlipList.php',
        'app/Livewire/Pages/Stock/StockMovements.php',
    ];
    foreach ($classes as $path) {
        $source = file_get_contents(base_path($path));
        expect($source)->toContain('function columnUrl')
            ->toContain('function rowActions')
            ->not->toContain("['label' => 'Aç'")
            ->not->toContain("['label' => 'Düzenle'")
            ->not->toContain("['label' => 'Detay'");
    }

    $reservation = file_get_contents(base_path('app/Livewire/Pages/Stock/ReservationList.php'));
    expect($reservation)->toContain("'label' => 'Rezervi Çöz'")
        ->not->toContain("['label' => 'Detay'");

    $table = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    expect($table)->toContain("'v65-record-link'")
        ->toContain('$this->columnUrl($row, $column)');
});

it('links the document number and authorized cari on sales and purchase lists', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/sales/document-list.blade.php'));
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithV65DocumentList.php'));

    expect($trait)->toContain("'detailRoute' => \$this->editRoute()")
        ->and($view)->toContain("route(\$detailRoute, ['id' => \$document->id])")
        ->toContain("route('contacts.show', ['contact' => \$document->contact_id])")
        ->not->toContain('wire:click="open(')
        ->not->toContain('<th>Eylem</th>');
});

it('removes redundant Open and Edit buttons from independent record lists', function (): void {
    $views = [
        'resources/views/livewire/pages/warehouse/transport-slip-list.blade.php',
        'resources/views/livewire/pages/catalog/category-list.blade.php',
        'resources/views/livewire/production/recipe-center.blade.php',
        'resources/views/livewire/production/production-order-center.blade.php',
        'resources/views/livewire/production/subcontracting-center.blade.php',
        'resources/views/livewire/returns/return-center.blade.php',
        'resources/views/livewire/channels/channel-account-center.blade.php',
        'resources/views/livewire/channels/channel-listing-center.blade.php',
        'resources/views/livewire/imports/import-center.blade.php',
        'resources/views/livewire/finance/finance-accounts.blade.php',
    ];
    foreach ($views as $path) {
        $source = file_get_contents(base_path($path));
        expect($source)->not->toContain('>Aç</button>')
            ->not->toContain('>Aç</a>')
            ->not->toContain('>Düzenle</button>')
            ->not->toContain('>Düzenle</a>');
    }

    $import = file_get_contents(base_path('resources/views/livewire/imports/import-center.blade.php'));
    $finance = file_get_contents(base_path('resources/views/livewire/finance/finance-accounts.blade.php'));
    $recipe = file_get_contents(base_path('resources/views/livewire/production/recipe-center.blade.php'));
    expect($import)->toContain('wire:click="editContainer(')
        ->toContain('wire:click="editPackage(')
        ->toContain('wire:click="editCost(')
        ->and($finance)->toContain('wire:click="toggleCash(')
        ->toContain('wire:click="toggleBank(')
        ->and($recipe)->toContain('wire:click="activate(');
});
