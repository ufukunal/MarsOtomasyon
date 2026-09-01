<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Inventory\Mobile\MobileWarehouseService;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

it('resolves scanner input through existing barcode identity and product code fallback', function (): void {
    $company = m27MobileCompany('M27-SCAN');
    $product = m27MobileProduct($company, 'MOB-001');
    Barcode::query()->create([
        'company_id' => $company->getKey(),
        'product_id' => $product->getKey(),
        'barcode' => '8690000002701',
        'is_primary' => true,
    ]);

    $service = app(MobileWarehouseService::class);
    $byBarcode = $service->lookupProduct((int) $company->getKey(), '8690000002701');
    $byCode = $service->lookupProduct((int) $company->getKey(), 'MOB-001');

    expect($byBarcode['product_id'])->toBe((int) $product->getKey())
        ->and($byBarcode['matched_by'])->toBe('barcode')
        ->and($byCode['product_id'])->toBe((int) $product->getKey())
        ->and($byCode['matched_by'])->toBe('code')
        ->and($byCode['barcode'])->toBe('8690000002701');

    expect(fn () => $service->lookupProduct((int) $company->getKey(), 'missing-scan'))
        ->toThrow(DomainException::class, 'not found for company');
});

it('claims mobile client operations exactly once and rejects replay or completion drift', function (): void {
    $company = m27MobileCompany('M27-IDEM');
    $service = app(MobileWarehouseService::class);
    $operationId = (string) Str::uuid();
    $payload = ['product_id' => 10, 'quantity' => 2, 'warehouse_id' => 4];

    $first = $service->claimOperation(
        (int) $company->getKey(),
        'scanner-a',
        $operationId,
        'stock-count-line',
        $payload,
    );
    $replay = $service->claimOperation(
        (int) $company->getKey(),
        'scanner-a',
        $operationId,
        'stock-count-line',
        ['warehouse_id' => 4, 'quantity' => 2, 'product_id' => 10],
    );

    expect($first['replay'])->toBeFalse()
        ->and($first['status'])->toBe('claimed')
        ->and($replay['replay'])->toBeTrue()
        ->and($replay['id'])->toBe($first['id']);

    $result = ['status' => 'accepted', 'domain_id' => 55];
    expect($service->completeOperation((int) $company->getKey(), $first['id'], $result))->toBe($result)
        ->and($service->completeOperation((int) $company->getKey(), $first['id'], ['domain_id' => 55, 'status' => 'accepted']))->toBe($result);

    expect(fn () => $service->claimOperation(
        (int) $company->getKey(),
        'scanner-a',
        $operationId,
        'stock-count-line',
        ['product_id' => 10, 'quantity' => 3, 'warehouse_id' => 4],
    ))->toThrow(DomainException::class, 'payload drift');

    expect(fn () => $service->completeOperation(
        (int) $company->getKey(),
        $first['id'],
        ['status' => 'accepted', 'domain_id' => 56],
    ))->toThrow(DomainException::class, 'result drift');
});

function m27MobileCompany(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
        'status' => 'active',
        'base_currency_code' => 'TRY',
        'timezone' => 'Europe/Istanbul',
    ]);
}

function m27MobileProduct(Company $company, string $code): Product
{
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code.'-CAT',
        'name' => 'Category '.$code,
        'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => mb_substr($code.'-UNIT', 0, 32),
        'name' => 'Unit '.$code,
        'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => mb_substr($code.'-TAX', 0, 64),
        'name' => 'Tax '.$code,
        'rate' => '20.000000',
        'is_active' => true,
    ]);

    return Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'status' => ProductStatus::Active,
        'name' => 'Product '.$code,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '50.000000',
    ]);
}
