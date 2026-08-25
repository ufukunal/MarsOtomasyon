<?php

use App\Foundation\Correlation\CorrelationContext;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\AuditEntry;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Actions\CreateProduct;
use App\Modules\Products\Actions\CreateProductData;
use App\Modules\Products\Actions\UpdateProduct;
use App\Modules\Products\Actions\UpdateProductData;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

it('updates one company product atomically with barcode replacement and before after audit', function (): void {
    [$company, $category, $unit, $tax] = m32ProductContext('M32-UPD-A');

    $product = app(CreateProduct::class)->handle(new CreateProductData(
        code: 'SKU-001',
        name: 'İlk Ürün',
        categoryId: $category->getKey(),
        unitId: $unit->getKey(),
        taxId: $tax->getKey(),
        salePriceNet: '100.000000',
        purchasePriceNet: '60.000000',
        primaryBarcode: '8690000000001',
        additionalBarcodes: ['8690000000002'],
    ));

    $updated = app(UpdateProduct::class)->handle($product->getKey(), new UpdateProductData(
        code: ' sku-002 ',
        status: ProductStatus::Inactive,
        name: ' Güncel Ürün ',
        categoryId: $category->getKey(),
        unitId: $unit->getKey(),
        taxId: $tax->getKey(),
        salePriceNet: '125.500000',
        purchasePriceNet: '70.250000',
        primaryBarcode: '8690000000010',
        additionalBarcodes: ['8690000000011'],
    ));

    $updated->refresh()->load('barcodes');

    expect($updated->company_id)->toBe($company->getKey())
        ->and($updated->code)->toBe('SKU-002')
        ->and($updated->statusEnum())->toBe(ProductStatus::Inactive)
        ->and($updated->name)->toBe('Güncel Ürün')
        ->and($updated->sale_price_net)->toBe('125.500000')
        ->and($updated->purchase_price_net)->toBe('70.250000')
        ->and($updated->barcodes->pluck('barcode')->sort()->values()->all())->toBe([
            '8690000000010',
            '8690000000011',
        ])
        ->and($updated->barcodes->where('is_primary', true)->sole()->barcode)->toBe('8690000000010')
        ->and(Barcode::query()->where('company_id', $company->getKey())->whereIn('barcode', ['8690000000001', '8690000000002'])->exists())->toBeFalse();

    $audit = AuditEntry::query()
        ->where('action', AuditAction::ProductUpdated->value)
        ->where('target_id', $product->getKey())
        ->firstOrFail();

    expect($audit->before_state['code'])->toBe('SKU-001')
        ->and($audit->after_state['code'])->toBe('SKU-002')
        ->and($audit->before_state['barcodes'])->toBe(['8690000000001', '8690000000002'])
        ->and($audit->after_state['barcodes'])->toBe(['8690000000010', '8690000000011']);
});

it('rolls back product and barcode changes when another product owns the requested barcode', function (): void {
    [, $category, $unit, $tax] = m32ProductContext('M32-UPD-B');

    $first = app(CreateProduct::class)->handle(new CreateProductData(
        code: 'FIRST',
        name: 'First',
        categoryId: $category->getKey(),
        unitId: $unit->getKey(),
        taxId: $tax->getKey(),
        primaryBarcode: '1111111111111',
    ));
    app(CreateProduct::class)->handle(new CreateProductData(
        code: 'SECOND',
        name: 'Second',
        categoryId: $category->getKey(),
        unitId: $unit->getKey(),
        taxId: $tax->getKey(),
        primaryBarcode: '2222222222222',
    ));

    expect(fn () => app(UpdateProduct::class)->handle($first->getKey(), new UpdateProductData(
        code: 'FIRST-CHANGED',
        status: ProductStatus::Inactive,
        name: 'Should Roll Back',
        categoryId: $category->getKey(),
        unitId: $unit->getKey(),
        taxId: $tax->getKey(),
        salePriceNet: '50',
        purchasePriceNet: '25',
        primaryBarcode: '2222222222222',
    )))->toThrow(ValidationException::class);

    $first->refresh()->load('barcodes');
    expect($first->code)->toBe('FIRST')
        ->and($first->statusEnum())->toBe(ProductStatus::Active)
        ->and($first->name)->toBe('First')
        ->and($first->barcodes->sole()->barcode)->toBe('1111111111111')
        ->and(AuditEntry::query()->where('action', AuditAction::ProductUpdated->value)->count())->toBe(0);
});

it('cannot update a product through another active company context', function (): void {
    [, $category, $unit, $tax] = m32ProductContext('M32-UPD-C-A');
    $product = app(CreateProduct::class)->handle(new CreateProductData(
        code: 'TENANT-SKU',
        name: 'Tenant Product',
        categoryId: $category->getKey(),
        unitId: $unit->getKey(),
        taxId: $tax->getKey(),
    ));

    [, $otherCategory, $otherUnit, $otherTax] = m32ProductContext('M32-UPD-C-B');

    expect(fn () => app(UpdateProduct::class)->handle($product->getKey(), new UpdateProductData(
        code: 'STOLEN',
        status: ProductStatus::Active,
        name: 'Cross Company',
        categoryId: $otherCategory->getKey(),
        unitId: $otherUnit->getKey(),
        taxId: $otherTax->getKey(),
        salePriceNet: '1',
        purchasePriceNet: '1',
    )))->toThrow(ModelNotFoundException::class);

    expect(Product::query()->findOrFail($product->getKey())->code)->toBe('TENANT-SKU');
});

/** @return array{Company, Category, Unit, Tax} */
function m32ProductContext(string $companyCode): array
{
    $company = Company::query()->create([
        'code' => $companyCode,
        'name' => 'Company '.$companyCode,
    ]);
    $actor = User::query()->create([
        'name' => 'M3.2 Product Actor '.$companyCode,
        'email' => strtolower($companyCode).'@m32-products.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'LIGHTING',
        'name' => 'Aydınlatma',
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

    app(ActiveCompanyContext::class)->set($company);
    app(CorrelationContext::class)->set('m3-2-product-update-test');
    test()->actingAs($actor);

    return [$company, $category, $unit, $tax];
}
