<?php

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Inventory\Mobile\MobileWarehouseService;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

it('resolves canonical barcode identity and product-code fallback inside the active company', function (): void {
    [$company, $product] = m27Context('M27-SCAN');
    Barcode::query()->create([
        'company_id' => $company->getKey(),
        'product_id' => $product->getKey(),
        'barcode' => '8690000027001',
        'type' => 'EAN13',
        'quantity' => '1',
    ]);

    $service = app(MobileWarehouseService::class);
    $barcodeResult = $service->lookupProduct((int) $company->getKey(), '8690000027001');
    $codeResult = $service->lookupProduct((int) $company->getKey(), (string) $product->code);

    expect($barcodeResult['product_id'])->toBe((int) $product->getKey())
        ->and($barcodeResult['matched_by'])->toBe('barcode')
        ->and($codeResult['product_id'])->toBe((int) $product->getKey())
        ->and($codeResult['matched_by'])->toBe('product_code')
        ->and($codeResult['barcode'])->toBe('8690000027001');
});

it('replays a completed mobile stock-count operation without executing it twice', function (): void {
    [$company, , $location] = m27Context('M27-IDEM');
    $service = app(MobileWarehouseService::class);
    $operationId = (string) Str::uuid();
    $payload = ['location_id' => (int) $location->getKey()];

    $first = $service->execute(
        (int) $company->getKey(),
        null,
        'scanner-a',
        $operationId,
        'stock_count.start',
        $payload,
    );
    $second = $service->execute(
        (int) $company->getKey(),
        null,
        'scanner-a',
        $operationId,
        'stock_count.start',
        $payload,
    );

    expect($first['replay'])->toBeFalse()
        ->and($second['replay'])->toBeTrue()
        ->and($second['data'])->toBe($first['data'])
        ->and(DB::table('mobile_client_operations')->count())->toBe(1)
        ->and(DB::table('stock_counts')->count())->toBe(1);
});

it('rejects idempotency payload drift and isolates operation identity by company', function (): void {
    [$firstCompany, , $firstLocation] = m27Context('M27-A');
    [$secondCompany, , $secondLocation] = m27Context('M27-B');
    $service = app(MobileWarehouseService::class);
    $operationId = (string) Str::uuid();

    $service->execute(
        (int) $firstCompany->getKey(),
        null,
        'scanner-shared',
        $operationId,
        'stock_count.start',
        ['location_id' => (int) $firstLocation->getKey()],
    );

    expect(fn () => $service->execute(
        (int) $firstCompany->getKey(),
        null,
        'scanner-shared',
        $operationId,
        'stock_count.start',
        ['location_id' => (int) $firstLocation->getKey() + 999],
    ))->toThrow(DomainException::class);

    $otherCompany = $service->execute(
        (int) $secondCompany->getKey(),
        null,
        'scanner-shared',
        $operationId,
        'stock_count.start',
        ['location_id' => (int) $secondLocation->getKey()],
    );

    expect($otherCompany['replay'])->toBeFalse()
        ->and(DB::table('mobile_client_operations')->count())->toBe(2)
        ->and(DB::table('stock_counts')->count())->toBe(2);
});

it('keeps the mobile warehouse extension enabled after delivery and exposes all milestone operation permissions', function (): void {
    $registry = app(FeatureRegistry::class);
    $service = app(MobileWarehouseService::class);

    expect($registry->enabled(FeatureKey::MobileWarehouse))->toBeTrue()
        ->and($service->permissionFor('goods_receipt.finalize'))->toBe('goods_receipts.manage')
        ->and($service->permissionFor('picking.consume'))->toBe('inventory.manage')
        ->and($service->permissionFor('dispatch.verify'))->toBe('dispatches.view')
        ->and($service->permissionFor('transfer.receive'))->toBe('inventory.manage')
        ->and($service->permissionFor('stock_count.scan'))->toBe('inventory.manage')
        ->and($service->permissionFor('subcontract.receive'))->toBe('subcontract.manage');
});

it('keeps the PWA service worker read-only for offline caching', function (): void {
    $worker = file_get_contents(public_path('mobile-warehouse-sw.js'));

    expect($worker)->toBeString()
        ->and($worker)->toContain("event.request.method !== 'GET'")
        ->and($worker)->not->toContain('backgroundSync')
        ->and($worker)->not->toContain('indexedDB');
});

/** @return array{Company, Product, WarehouseLocation} */
function m27Context(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'M27-CAT-'.$code,
        'name' => 'Mobil Depo',
        'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'ADET',
        'name' => 'Adet',
        'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'SKU-'.$code,
        'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '60.000000',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'WH-'.$code,
        'name' => 'Mobil Depo',
        'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'code' => 'MOBILE',
        'name' => 'Mobil Raf',
        'is_active' => true,
    ]);

    return [$company, $product, $location];
}
