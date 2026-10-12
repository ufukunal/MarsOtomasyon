<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

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
        'Taşıma Bilgileri', 'Koli Adedi', 'Toplam Ağırlık',
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
        ->toContain('Filtreler')
        ->toContain('wsl-field')
        ->toContain('Listede ara...');

    $printing = file_get_contents(base_path('resources/views/printing/transport-slip.blade.php'));
    expect($printing)->toContain('$copy < 2')
        ->toContain('$i <= $slip->package_count')
        ->toContain('OpaqueQrSvg::render')
        ->toContain('class="label-header"');
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
        ->and($editor)->toContain('#[Locked]')
        ->toContain("request()->routeIs('warehouse.transport.edit')")
        ->toContain('abort_unless($this->mode')
        ->toContain("'readonly' => \$this->mode === 'detail'")
        ->toContain("redirectRoute('warehouse.transport.edit'")
        ->toContain("\$duplicate->status = 'draft'")
        ->toContain('$duplicate->tracking_number = null')
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

it('previews unpersisted form values without creating any stock movement', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipEditor.php'));
    $form = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-editor.blade.php'));

    expect($editor)->toContain('private function draftPrintHtml(')
        ->toContain('new TransportSlip([')
        ->toContain("'TASLAK'")
        ->toContain('$this->slip !== null ? $this->slip->number')
        ->toContain("draftQrSeed = ''")
        ->toContain("->setRelation('contact'")
        ->toContain('previewHtml')
        ->toContain('validate($this->rules(), [], $this->fieldNames())')
        ->and($form)->toContain('srcdoc="{{ $previewHtml }}"')
        ->toContain('Fiş Yazdır / PDF')
        ->toContain('Etiket Yazdır');
});

it('prints exactly two A5 transport slips on one A4 without product details', function (): void {
    $view = file_get_contents(base_path('resources/views/printing/transport-slip.blade.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintTransportSlipController.php'));

    expect($view)->toContain('$copy < 2')
        ->toContain('class="sheet a4"')
        ->not->toContain('$slip->items->chunk(')
        ->not->toContain('<table class="items">')
        ->not->toContain('$item->product')
        ->toContain('OpaqueQrSvg::render')
        ->toContain('@if($autoprint)')
        ->not->toContain('->take(8)')
        ->and($controller)->toContain("request()->boolean('autoprint')");
});

it('routes old Ambar bookmarks to v65 and retains G-207 stock documents separately', function (): void {
    expect(route('warehouse.transport.index', [], false))->toBe('/ambar/fisler')
        ->and(route('warehouse.transport.create', [], false))->toBe('/ambar/fisler/yeni')
        ->and(route('stock.warehouse-slips.index', [], false))->toBe('/stok/duzeltme-fisleri')
        ->and(route('stock.warehouse-slips.create', [], false))->toBe('/stok/duzeltme-fisleri/yeni');

    $legacyList = Route::getRoutes()->match(
        Request::create('/stok/ambar-fisleri', 'GET'),
    );
    $legacyCreate = Route::getRoutes()->match(
        Request::create('/stok/ambar-fisleri/yeni', 'GET'),
    );
    $legacyShow = Route::getRoutes()->match(
        Request::create('/stok/ambar-fisleri/42', 'GET'),
    );

    expect($legacyList->getName())->toBe('warehouse.transport.legacy.index')
        ->and($legacyCreate->getName())->toBe('warehouse.transport.legacy.create')
        ->and($legacyShow->getName())->toBe('stock.warehouse-slips.legacy.show');

    $stockTable = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    expect($stockTable)->toContain("['Stok Düzeltme Fişleri',");
});

it('keeps v65 general and table search inputs independent', function (): void {
    $component = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipList.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-list.blade.php'));

    expect($component)->toContain("public string \$tableSearch = ''")
        ->toContain('trim($this->tableSearch)')
        ->toContain("'search', 'tableSearch', 'status'")
        ->and($view)->toContain('wire:model.live.debounce.300ms="search"')
        ->toContain('wire:model.live.debounce.300ms="tableSearch"');
});

it('allows server-side product searching beyond the first page and distinguishes draft printing from preview', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipEditor.php'));
    $form = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-editor.blade.php'));

    expect($editor)->toContain("public string \$productSearch = ''")
        ->toContain("->orWhere('name', 'ilike', \$needle)")
        ->toContain('limit(80)')
        ->toContain('public bool $previewPrint = false')
        ->toContain('openPreview(string $format, bool $print = false)')
        ->and($form)->toContain('wire:model.live.debounce.300ms="productSearch"')
        ->toContain("openPreview('slip', true)")
        ->toContain("openPreview('labels', true)")
        ->toContain('onload="this.contentWindow.print()"');
});

it('searches carrier without exposing obsolete destination warehouse values', function (): void {
    $list = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipList.php'));

    expect($list)->toContain("->orWhere('carrier', 'ilike', \$text)")
        ->toContain("->orWhere('carrier', 'ilike', \$needle)")
        ->not->toContain("->orWhere('destination_warehouse'");
});

it('exports authorized period-local ambar slips as Excel-compatible CSV without spreadsheet formula execution', function (): void {
    $routes = file_get_contents(base_path('routes/web.php'));
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/ExportTransportSlipController.php'));

    expect($routes)->toContain("name('warehouse.transport.export')")
        ->and($layout)->toContain("route('warehouse.transport.export'")
        ->toContain('data-wsl-print-slip')
        ->and($controller)->toContain("'warehouse_slips.view'")
        ->toContain("with(['contact', 'items.product'])")
        ->toContain('streamDownload(')
        ->toContain('safeCell(');
});

it('renders Turkish validation feedback for each incomplete transport slip field', function (): void {
    $previous = app()->getLocale();
    app()->setLocale('tr');

    try {
        $validator = Validator::make(
            ['deliveryAddress' => '', 'contactId' => null],
            ['deliveryAddress' => ['required'], 'contactId' => ['required']],
            [],
            ['deliveryAddress' => 'Teslimat Adresi', 'contactId' => 'Firma / Cari'],
        );

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->first('deliveryAddress'))->toBe('Teslimat Adresi alanı zorunludur.')
            ->and($validator->errors()->first('contactId'))->toBe('Firma / Cari alanı zorunludur.');

        $component = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipEditor.php'));
        $view = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-editor.blade.php'));

        expect($component)->toContain('$this->validate($this->rules(), [], $this->fieldNames())')
            ->toContain("'deliveryAddress' => 'Teslimat Adresi'")
            ->toContain('"items.{$index}.product_id"')
            ->and($view)->toContain("@error('deliveryAddress')")
            ->toContain("@error('contactId')")
            ->toContain("@error('items.'.\$i.'.product_id')")
            ->toContain('class="wsl-validation-summary"')
            ->not->toContain('@foreach($errors->all() as $error)');
    } finally {
        app()->setLocale($previous);
    }
});

it('defers transport slip database lookup until after period context is initialized', function (): void {
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintTransportSlipController.php'));
    $route = Route::getRoutes()->getByName('warehouse.transport.print');

    expect($route)->not->toBeNull()
        ->and($route->wheres['slip'] ?? null)->not->toBeNull()
        ->and($controller)->toContain('public function __invoke(int $slip, string $format): View')
        ->toContain('PeriodContext::ensure()')
        ->toContain('->findOrFail($slip)')
        ->not->toContain('public function __invoke(TransportSlip $slip');
});

it('renders both A4 halves and carton labels without requiring a database', function (): void {
    $slip = (object) [
        'number' => 'AF-2026-00001',
        'slip_date' => new DateTimeImmutable('2026-10-12'),
        'shipping_type' => 'Ambar',
        'carrier' => 'Test Ambar',
        'destination_warehouse' => 'Ankara',
        'package_count' => 3,
        'weight_kg' => '0.00',
        'tracking_number' => '',
        'order_reference' => '',
        'dispatch_reference' => '',
        'delivery_company' => 'Deneme Ltd',
        'delivery_person' => '',
        'delivery_phone' => '03120000000',
        'delivery_city' => 'Ankara',
        'delivery_district' => 'Çankaya',
        'delivery_address' => 'Test adresi',
        'note' => '',
        'contact' => (object) ['code' => 'CR001', 'title' => 'Deneme Ltd'],
        'items' => collect(array_fill(0, 7, (object) [
            'quantity' => '2.000',
            'product' => (object) [
                'code' => 'UR001', 'name' => 'Test Ürün',
                'unit' => (object) ['name' => 'Adet'],
            ],
        ])),
    ];

    config(['app.key' => 'test-qr-key']);

    $data = ['slip' => $slip, 'warnings' => 'KIRILABİLİR', 'autoprint' => false, 'qrScope' => 'test-company:period:slip'];
    $html = view('printing.transport-slip', [...$data, 'format' => 'slip'])->render();

    $slipBody = substr($html, strpos($html, '<main class="sheet a4">'), strpos($html, '</main>') - strpos($html, '<main class="sheet a4">'));

    expect(substr_count($slipBody, 'class="head-date"'))->toBe(2)
        ->and(substr_count($slipBody, 'class="head-token"'))->toBe(2)
        ->and(substr_count($slipBody, 'Kaşe / Onay'))->toBe(2)
        ->and($html)->toContain('@page{size:A4 landscape;margin:0}')
        ->not->toContain('@page{size:A4 portrait;margin:0}')
        ->and($slipBody)->not->toContain('<h1>AMBAR FİŞİ</h1>')
        ->not->toContain('AF-2026-00001')
        ->toContain('12.10.2026')
        ->toContain('KIRILABİLİR')
        ->not->toContain('CR001')
        ->not->toContain('Takip / Teslim No')
        ->not->toContain('Sipariş / İrsaliye')
        ->not->toContain('UR001')
        ->not->toContain('Test Ürün')
        ->not->toContain('2,000')
        ->not->toContain('Teslim Eden')
        ->not->toContain('Teslim Alan')
        ->not->toContain('<table class="items">');

    $labels = view('printing.transport-slip', [...$data, 'format' => 'labels'])->render();
    expect(substr_count($labels, 'class="label-qr"'))->toBe(3)
        ->and($labels)->toContain('@page{size:A4 portrait;margin:0}')
        ->not->toContain('@page{size:A4 landscape;margin:0}')
        ->toContain('1/3')
        ->toContain('3/3')
        ->not->toContain('SEVKİYAT ETİKETİ');
});

it('groups the warehouse footer actions consistently without changing Livewire behavior', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-editor.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-warehouse.css'));

    expect($view)->toContain('class="wsl-footer-right"')
        ->toContain('wsl-footer-group-print')
        ->toContain('wsl-footer-group-document')
        ->toContain('aria-label="Fiş"')
        ->toContain('aria-label="Etiket"')
        ->toContain("wire:click=\"openPreview('slip')\"")
        ->toContain("wire:click=\"openPreview('slip', true)\"")
        ->toContain("wire:click=\"openPreview('labels')\"")
        ->toContain("wire:click=\"openPreview('labels', true)\"")
        ->toContain('data-wsl-print-slip')
        ->toContain('wire:click="duplicate"')
        ->toContain('wire:click="advance"')
        ->toContain('wire:click="cancel"')
        ->toContain('wire:confirm="Bu ambar fişi iptal edilecek.')
        ->toContain('type="submit" class="wsl-footer-btn wsl-footer-btn-primary"')
        ->and($css)->toContain('.wsl-footer .wsl-footer-btn')
        ->toContain('.wsl-footer-group-print')
        ->toContain('.wsl-footer-btn-danger')
        ->toContain('@media(max-width:610px)');
});

it('removes the redundant destination warehouse field without losing existing period data', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipEditor.php'));
    $form = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-editor.blade.php'));
    $print = file_get_contents(base_path('resources/views/printing/transport-slip.blade.php'));
    $export = file_get_contents(base_path('app/Http/Controllers/ExportTransportSlipController.php'));
    $migration = file_get_contents(base_path('database/migrations/period/0001_12_01_000010_create_transport_slips_tables.php'));

    expect($form)->toContain('Taşıma Bilgileri')
        ->not->toContain('Gideceği Ambar')
        ->not->toContain('wire:model="destinationWarehouse"')
        ->and($editor)->not->toContain('public string $destinationWarehouse')
        ->not->toContain("'destinationWarehouse' => ['required'")
        ->toContain("'destination_warehouse' => \$slip->destination_warehouse ?: \$data['carrier']")
        ->toContain("'destination_warehouse' => \$this->slip?->destination_warehouse ?: \$this->carrier")
        ->and($print)->not->toContain('Gideceği Ambar')
        ->toContain('<th>Taşıyıcı / Ambar</th><td>{{ $slip->carrier }}</td>')
        ->and($export)->not->toContain('Gideceği Ambar')
        ->not->toContain('$slip->destination_warehouse,')
        ->and($migration)->toContain("'destination_warehouse'");
});

it('keeps opaque QR identifiers stable per slip and distinct per parcel', function (): void {
    $key = 'test-qr-key';
    $scope = 'company:period:slip-42';
    $slipToken = \App\Support\Printing\Label\OpaqueQrSvg::token($scope, 0, $key);
    $parcelOne = \App\Support\Printing\Label\OpaqueQrSvg::token($scope, 1, $key);
    $parcelTwo = \App\Support\Printing\Label\OpaqueQrSvg::token($scope, 2, $key);

    expect($slipToken)->toMatch('/\\A[A-F0-9]{32}\\z/')
        ->and($parcelOne)->toMatch('/\\A[A-F0-9]{32}\\z/')
        ->and($parcelTwo)->toMatch('/\\A[A-F0-9]{32}\\z/')
        ->and(\App\Support\Printing\Label\OpaqueQrSvg::token($scope, 1, $key))->toBe($parcelOne)
        ->and($parcelOne)->not->toBe($parcelTwo)
        ->and($slipToken)->not->toBe($parcelOne);
});

it('prints confidential QR codes rather than public barcodes on transport slips and parcel labels', function (): void {
    $print = file_get_contents(base_path('resources/views/printing/transport-slip.blade.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintTransportSlipController.php'));
    $editor = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipEditor.php'));

    expect($print)->toContain('OpaqueQrSvg::render')
        ->toContain('OpaqueQrSvg::token($qrScope, 0,')
        ->toContain('OpaqueQrSvg::token($qrScope, $i,')
        ->toContain('font-size:88px')
        ->toContain('font-size:33px')
        ->toContain('font-size:18px')
        ->toContain('font-size:29px')
        ->toContain('class="label-city"')
        ->toContain('class="label-district"')
        ->not->toContain('Code39Svg::render')
        ->not->toContain('<table class="items">')
        ->not->toContain('$item->product')
        ->and($controller)->toContain("'qrScope' => implode(':',")
        ->and($editor)->toContain("'qrScope' => \$this->slip !== null");
});

it('positions each parcel QR above the divider without the former heading or slip number', function (): void {
    $print = file_get_contents(base_path('resources/views/printing/transport-slip.blade.php'));

    $labelTemplate = substr($print, strpos($print, '<main class="sheet" style="width:210mm">'));

    expect($labelTemplate)->toContain('class="label-header"')
        ->toContain('class="label-company"')
        ->toContain('class="label-city"')
        ->and($print)->toContain('.label-header .label-qr{width:25mm')
        ->not->toContain('SEVKİYAT ETİKETİ')
        ->not->toContain('<p>{{ $slip->number }}</p>');

    $headerStart = strpos($labelTemplate, '<header class="label-header">');
    $headerEnd = strpos($labelTemplate, '</header>', $headerStart);
    $qrPosition = strpos($labelTemplate, 'OpaqueQrSvg::token($qrScope, $i,');
    $counterPosition = strpos($labelTemplate, '{{ $i }}/{{ $slip->package_count }}</strong>');

    expect($qrPosition)->toBeGreaterThan($headerStart)
        ->toBeLessThan($headerEnd)
        ->and($counterPosition)->toBeGreaterThan($qrPosition)
        ->toBeLessThan($headerEnd)
        ->and(substr_count($labelTemplate, 'OpaqueQrSvg::token($qrScope, $i,'))->toBe(1);
});

it('prints only date and the matching opaque QR identifier in the A5 header', function (): void {
    $print = file_get_contents(base_path('resources/views/printing/transport-slip.blade.php'));
    $slipTemplate = substr($print, strpos($print, "@if(\$format === 'slip')"), strpos($print, '@else', strpos($print, "@if(\$format === 'slip')")) - strpos($print, "@if(\$format === 'slip')"));

    expect($slipTemplate)->toContain('class="head-date"')
        ->toContain("{{ \$slip->slip_date->format('d.m.Y') }}")
        ->toContain('class="head-token"')
        ->toContain('{{ $slipQrToken }}')
        ->toContain('OpaqueQrSvg::render($slipQrToken)')
        ->toContain('Kaşe / Onay')
        ->not->toContain('<h1>AMBAR FİŞİ</h1>')
        ->not->toContain('{{ $slip->number }}')
        ->not->toContain('contact?->title')
        ->not->toContain('contact?->code')
        ->not->toContain('Takip / Teslim No')
        ->not->toContain('Sipariş / İrsaliye')
        ->not->toContain('signatures')
        ->not->toContain('Teslim Eden')
        ->not->toContain('Teslim Alan');
});

it('sizes the parcel number against the QR and keeps large carton counts legible', function (): void {
    $print = file_get_contents(base_path('resources/views/printing/transport-slip.blade.php'));

    expect($print)->toContain('.label-header .label-qr{width:25mm;height:25mm;')
        ->toContain('min-height:25mm;font-size:88px')
        ->toContain('strong.parcel-count-wide{font-size:70px}')
        ->toContain('strong.parcel-count-xwide{font-size:51px}')
        ->toContain("'parcel-count-wide' => \$slip->package_count >= 10")
        ->toContain("'parcel-count-xwide' => \$slip->package_count >= 100");
});

it('lays out two upright A5 slips side by side on one landscape A4 with a vertical cut divider', function (): void {
    $print = file_get_contents(base_path('resources/views/printing/transport-slip.blade.php'));

    expect($print)->toContain('.a4{width:297mm;height:210mm;display:grid;grid-template-columns:148.5mm 148.5mm;')
        ->toContain('.copy{width:148.5mm;height:210mm;')
        ->toContain('.copy:first-child{border-right:1px dashed')
        ->toContain('writing-mode:vertical-rl')
        ->toContain("'Ambar Fişi (Yatay A4 / Yan Yana 2 × Dik A5)'")
        ->not->toContain('grid-template-rows:148.5mm 148.5mm')
        ->not->toContain('.copy:first-child{border-bottom:');
});
