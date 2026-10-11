<?php

use Illuminate\Support\Facades\Route;

it('maps approved v65 ambar transport separately from legacy stock adjustments', function (): void {
    $ref = file_get_contents(base_path('reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html'));
    expect($ref)->toContain('warehouse_slip_list_v165')
        ->toContain('warehouse_slip_new_v165')
        ->toContain('Taşıma / Gideceği Ambar')
        ->toContain('Firma / Teslimat İletişimi')
        ->toContain('iki A5 kopyasını');

    foreach ([
        'warehouse.transport.index', 'warehouse.transport.create',
        'warehouse.transport.show', 'warehouse.transport.edit', 'warehouse.transport.print',
        'stock.warehouse-slips.index', 'stock.warehouse-slips.show',
    ] as $route) {
        expect(Route::has($route))->toBeTrue();
    }

    $nav = collect(config('navigation'));
    $warehouse = array_column($nav->firstWhere('label', 'Ambar')['items'], 'route');
    $stock = array_column($nav->firstWhere('label', 'Ürün / Stok')['items'], 'route');

    expect($warehouse)->toBe(['warehouse.transport.index', 'warehouse.transport.create'])
        ->and($stock)->toContain('stock.warehouse-slips.index');
});

it('keeps transport lifecycle independent from physical stock posting', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipEditor.php'));
    $legacy = file_get_contents(base_path('app/Actions/Stock/PostWarehouseSlip.php'));
    $model = file_get_contents(base_path('app/Models/Period/TransportSlip.php'));

    expect($editor)->toContain("'ready' => 'handed_over'")
        ->toContain("'handed_over' => 'completed'")
        ->toContain("'shipping_type'")
        ->toContain("'destination_warehouse'")
        ->toContain("'delivery_address'")
        ->toContain("'package_count'")
        ->toContain('PeriodContext::ensureWritable()')
        ->not->toContain('$this->recordMovement')
        ->and($model)->toContain('class TransportSlip')
        ->and($legacy)->toContain('recordMovement->handle(');
});

it('preserves approved field groups, filters and A4/A5 and numbered carton printing', function (): void {
    $form = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-editor.blade.php'));

    foreach ([
        'Fiş Bilgileri', 'Firma / Cari', 'Sipariş Referansı', 'İrsaliye Referansı',
        'Taşıma / Gideceği Ambar', 'Koli Adedi', 'Gideceği Ambar', 'Toplam Ağırlık',
        'Cam İçeriyor', 'Kırılabilir / Hassas', 'Dik Taşınmalı', 'Üstüne Yük Konmaz',
        'Ürünler / Koli İçeriği', 'Firma / Teslimat İletişimi', 'Teslimat Adresi',
        'Etiket Önizle', 'Fiş Önizle',
    ] as $field) {
        expect($form)->toContain($field);
    }

    $list = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-list.blade.php'));
    expect($list)->toContain('Ambar Fişi Listesi')
        ->toContain('Ambar / Nakliye')
        ->toContain('Cam Durumu')
        ->toContain('Kırılabilir')
        ->toContain('Filtreler');

    $printing = file_get_contents(base_path('resources/views/printing/transport-slip.blade.php'));
    expect($printing)->toContain('$copy < 2')
        ->toContain('$i <= $slip->package_count')
        ->toContain('Code39Svg::render')
        ->toContain('SEVKİYAT ETİKETİ');
});

it('requires a distinct period migration and keeps the UAT migration isolated', function (): void {
    $migration = file_get_contents(base_path('database/migrations/period/0001_12_01_000010_create_transport_slips_tables.php'));
    $script = file_get_contents(base_path('ops/uat-migrate-transport-slip.sh'));

    expect($migration)->toContain("'transport_slips'")
        ->toContain("'transport_slip_items'")
        ->not->toContain("Schema::connection('master')->create")
        ->and($script)->toContain('mars_uat_demo_2026')
        ->toContain('mars-uat-lab')
        ->toContain('--database=period')
        ->not->toContain('docker volume rm')
        ->not->toContain('down -v');
});

it('implements separate read-only detail and editing routes and safe copy-to-edit', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipEditor.php'));
    $routes = file_get_contents(base_path('routes/web.php'));
    $list = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-list.blade.php'));

    expect($routes)->toContain("name('warehouse.transport.edit')")
        ->and($editor)->toContain("#[Locked]")
        ->toContain("request()->routeIs('warehouse.transport.edit')")
        ->toContain('abort_unless($this->mode')
        ->toContain("'readonly' => \$this->mode === 'detail'")
        ->toContain("redirectRoute('warehouse.transport.edit'")
        ->toContain("\$duplicate->status = 'draft'")
        ->toContain("\$duplicate->tracking_number = null")
        ->and($list)->toContain("route('warehouse.transport.edit'");
});

it('matches two-column left-labeled form, real choices and four distinct print actions', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-warehouse.css'));
    $form = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-editor.blade.php'));
    $editor = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipEditor.php'));

    expect($css)->toContain('grid-template-columns:repeat(2,minmax(0,1fr))')
        ->toContain('grid-template-columns:155px minmax(0,1fr)')
        ->toContain('position:sticky;bottom:0')
        ->and($form)->toContain('wsl-head-actions')
        ->toContain('wire:model="orderReference"')
        ->toContain('wire:model="dispatchReference"')
        ->toContain('wire:model.live="carrierOption"')
        ->toContain("openPreview('slip')")
        ->toContain("openPreview('labels')")
        ->toContain('Fiş Yazdır / PDF')
        ->toContain('Etiket Yazdır')
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->not->toContain('Çıkış Deposu')
        ->and($editor)->toContain('DocumentType::SalesOrder')
        ->toContain('DocumentType::Dispatch')
        ->toContain('carrierOptions')
        ->toContain('deliveryCompany ?:')
        ->toContain('deliveryAddress ?:');
});

it('prints two A5 slips on A4 without dropping products after five rows', function (): void {
    $view = file_get_contents(base_path('resources/views/printing/transport-slip.blade.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintTransportSlipController.php'));

    expect($view)->toContain('$slip->items->chunk(5)')
        ->toContain('$copy < 2')
        ->toContain('Code39Svg::render')
        ->toContain('@if($autoprint)')
        ->not->toContain('->take(8)')
        ->and($controller)->toContain("request()->boolean('autoprint')");
});
