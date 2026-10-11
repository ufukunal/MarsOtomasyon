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
        'warehouse.transport.show', 'warehouse.transport.print',
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
