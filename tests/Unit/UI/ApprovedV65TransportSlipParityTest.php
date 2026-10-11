<?php

use App\Models\Period\Contact;
use App\Models\Period\TransportSlip;
use App\Models\Period\TransportSlipItem;
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
        ->toContain('Filtreler')
        ->toContain('wsl-field')
        ->toContain('Listede ara...');

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
        ->toContain('new TransportSlipItem([')
        ->toContain('setRelation(')
        ->toContain('previewHtml')
        ->toContain('validate($this->rules(), [], $this->fieldNames())')
        ->and($form)->toContain('srcdoc="{{ $previewHtml }}"')
        ->toContain('Fiş Yazdır / PDF')
        ->toContain('Etiket Yazdır');
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

it('includes both carrier and destination warehouse in transport list searches', function (): void {
    $list = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipList.php'));

    expect($list)->toContain("->orWhere('destination_warehouse', 'ilike', \$text)")
        ->toContain("->orWhere('destination_warehouse', 'ilike', \$needle)");
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

it('renders both saved-slip format templates from period-free transport fixture', function (): void {
    $slip = new TransportSlip([
        'number' => 'AF-2026-00001',
        'slip_date' => '2026-10-12',
        'shipping_type' => 'Ambar',
        'carrier' => 'Test Ambar',
        'destination_warehouse' => 'Ankara',
        'package_count' => 3,
        'delivery_company' => 'Deneme Ltd',
        'delivery_phone' => '03120000000',
        'delivery_city' => 'Ankara',
        'delivery_district' => 'Çankaya',
        'delivery_address' => 'Test adresi',
    ]);
    $slip->setRelation('contact', new Contact(['code' => 'CR001', 'title' => 'Deneme Ltd']));
    $item = new TransportSlipItem(['quantity' => '2.000']);
    $item->setRelation('product', null);
    $slip->setRelation('items', collect([$item]));

    $data = ['slip' => $slip, 'warnings' => 'KIRILABİLİR', 'autoprint' => false];

    $html = view('printing.transport-slip', [...$data, 'format' => 'slip'])->render();
    expect(substr_count($html, '<h1>AMBAR FİŞİ</h1>'))->toBe(2)
        ->and($html)->toContain('AF-2026-00001')
        ->toContain('12.10.2026')
        ->toContain('2,000')
        ->toContain('KIRILABİLİR');

    $labels = view('printing.transport-slip', [...$data, 'format' => 'labels'])->render();
    expect(substr_count($labels, 'SEVKİYAT ETİKETİ'))->toBe(3)
        ->and($labels)->toContain('1/3')
        ->toContain('3/3');
});
