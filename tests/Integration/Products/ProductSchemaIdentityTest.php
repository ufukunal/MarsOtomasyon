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
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

it('creates a normalized company-scoped SKU with net prices tax metadata and barcode identities', function (): void {
    [$company, $actor] = m31Context('M31-A');
    $category = m31Category($company, 'LIGHTING', 'Aydınlatma');
    $unit = m31Unit($company, 'ADET', 'Adet');
    $tax = m31Tax($company, 'KDV20', 'KDV %20', '20');

    $product = app(CreateProduct::class)->handle(new CreateProductData(
        code: ' lamp-001 ',
        name: '  Mars Sarkıt Avize  ',
        categoryId: (int) $category->getKey(),
        unitId: (int) $unit->getKey(),
        taxId: (int) $tax->getKey(),
        salePriceNet: '001250.5',
        purchasePriceNet: '700',
        primaryBarcode: '8690000000001',
        additionalBarcodes: ['MARS-LAMP-001'],
    ));

    $product->refresh()->load(['category', 'unit', 'tax', 'barcodes']);

    expect($product->company_id)->toBe($company->getKey())
        ->and($product->code)->toBe('LAMP-001')
        ->and($product->statusEnum())->toBe(ProductStatus::Active)
        ->and($product->name)->toBe('Mars Sarkıt Avize')
        ->and($product->category?->getKey())->toBe($category->getKey())
        ->and($product->unit->getKey())->toBe($unit->getKey())
        ->and($product->tax->getKey())->toBe($tax->getKey())
        ->and($product->sale_price_net)->toBe('1250.500000')
        ->and($product->purchase_price_net)->toBe('700.000000')
        ->and($product->barcodes)->toHaveCount(2)
        ->and($product->barcodes->where('is_primary', true)->first()?->barcode)->toBe('8690000000001');

    expect($product->barcodes->pluck('barcode')->all())
        ->toContain('8690000000001', 'MARS-LAMP-001');

    $audit = AuditEntry::query()->where('action', AuditAction::ProductCreated->value)->firstOrFail();
    expect($audit->company_id)->toBe($company->getKey())
        ->and($audit->actor_user_id)->toBe($actor->getKey())
        ->and($audit->correlation_id)->toBe('m3-1-product-test')
        ->and($audit->after_state['code'])->toBe('LAMP-001')
        ->and($audit->after_state['sale_price_net'])->toBe('1250.500000')
        ->and($audit->after_state['purchase_price_net'])->toBe('700.000000');
});

it('allows the same SKU code and barcode in different companies while keeping identities tenant scoped', function (): void {
    [$companyA] = m31Context('M31-B-A');
    [$categoryA, $unitA, $taxA] = m31References($companyA, 'A');
    $first = app(CreateProduct::class)->handle(m31ProductData($categoryA, $unitA, $taxA, 'SHARED-SKU', '8691111111111'));

    [$companyB] = m31Context('M31-B-B');
    [$categoryB, $unitB, $taxB] = m31References($companyB, 'B');
    $second = app(CreateProduct::class)->handle(m31ProductData($categoryB, $unitB, $taxB, 'shared-sku', '8691111111111'));

    expect($first->company_id)->toBe($companyA->getKey())
        ->and($second->company_id)->toBe($companyB->getKey())
        ->and(Product::query()->where('code', 'SHARED-SKU')->count())->toBe(2)
        ->and(Barcode::query()->where('barcode', '8691111111111')->count())->toBe(2);
});

it('rejects duplicate product and barcode identities and invalid normalized prices before persistence', function (): void {
    [$company] = m31Context('M31-C');
    [$category, $unit, $tax] = m31References($company, 'C');

    app(CreateProduct::class)->handle(m31ProductData($category, $unit, $tax, 'SKU-001', '8692222222222'));

    expect(fn () => app(CreateProduct::class)->handle(m31ProductData($category, $unit, $tax, 'sku-001', '8693333333333')))
        ->toThrow(ValidationException::class);

    expect(fn () => app(CreateProduct::class)->handle(m31ProductData($category, $unit, $tax, 'SKU-002', '8692222222222')))
        ->toThrow(ValidationException::class);

    expect(fn () => app(CreateProduct::class)->handle(new CreateProductData(
        code: 'SKU-003',
        name: 'Duplicate input barcode',
        categoryId: (int) $category->getKey(),
        unitId: (int) $unit->getKey(),
        taxId: (int) $tax->getKey(),
        salePriceNet: '10',
        purchasePriceNet: '5',
        primaryBarcode: 'DUPLICATE',
        additionalBarcodes: ['DUPLICATE'],
    )))->toThrow(ValidationException::class);

    expect(fn () => app(CreateProduct::class)->handle(new CreateProductData(
        code: 'SKU-BAD-PRICE',
        name: 'Bad Price',
        categoryId: (int) $category->getKey(),
        unitId: (int) $unit->getKey(),
        taxId: (int) $tax->getKey(),
        salePriceNet: '-0.01',
    )))->toThrow(ValidationException::class);

    expect(Product::query()->count())->toBe(1);
});

it('rejects cross-company and inactive category unit or tax references in the application use case', function (): void {
    [$companyA] = m31Context('M31-D-A');
    [$categoryA, $unitA, $taxA] = m31References($companyA, 'A');

    $inactiveCategory = m31Category($companyA, 'INACTIVE-CATEGORY', 'Pasif Kategori', false);
    $inactiveUnit = m31Unit($companyA, 'INACTIVE-UNIT', 'Pasif Birim', false);
    $inactiveTax = m31Tax($companyA, 'INACTIVE-TAX', 'Pasif Vergi', '20', false);

    [$companyB] = m31Context('M31-D-B');
    [$categoryB, $unitB, $taxB] = m31References($companyB, 'B');

    app(ActiveCompanyContext::class)->set($companyA);

    expect(fn () => app(CreateProduct::class)->handle(m31ProductData($categoryB, $unitA, $taxA, 'BAD-CATEGORY', null)))
        ->toThrow(ValidationException::class);
    expect(fn () => app(CreateProduct::class)->handle(m31ProductData($categoryA, $unitB, $taxA, 'BAD-UNIT', null)))
        ->toThrow(ValidationException::class);
    expect(fn () => app(CreateProduct::class)->handle(m31ProductData($categoryA, $unitA, $taxB, 'BAD-TAX', null)))
        ->toThrow(ValidationException::class);
    expect(fn () => app(CreateProduct::class)->handle(m31ProductData($inactiveCategory, $unitA, $taxA, 'INACTIVE-CATEGORY-SKU', null)))
        ->toThrow(ValidationException::class);
    expect(fn () => app(CreateProduct::class)->handle(m31ProductData($categoryA, $inactiveUnit, $taxA, 'INACTIVE-UNIT-SKU', null)))
        ->toThrow(ValidationException::class);
    expect(fn () => app(CreateProduct::class)->handle(m31ProductData($categoryA, $unitA, $inactiveTax, 'INACTIVE-TAX-SKU', null)))
        ->toThrow(ValidationException::class);

    expect(Product::query()->count())->toBe(0);
});

it('keeps SKU price and cross-company references protected at the PostgreSQL boundary', function (): void {
    [$companyA] = m31Context('M31-E-A');
    [$categoryA, $unitA, $taxA] = m31References($companyA, 'A');
    [$companyB] = m31Context('M31-E-B');
    [$categoryB, $unitB, $taxB] = m31References($companyB, 'B');

    $validId = m31DirectProductInsert(
        companyId: (int) $companyA->getKey(),
        code: 'DB-SKU',
        categoryId: (int) $categoryA->getKey(),
        unitId: (int) $unitA->getKey(),
        taxId: (int) $taxA->getKey(),
    );

    expect(fn () => m31DirectProductInsert(
        companyId: (int) $companyA->getKey(),
        code: 'db-sku',
        categoryId: (int) $categoryA->getKey(),
        unitId: (int) $unitA->getKey(),
        taxId: (int) $taxA->getKey(),
    ))->toThrow(QueryException::class);

    expect(fn () => m31DirectProductInsert(
        companyId: (int) $companyA->getKey(),
        code: 'BAD-CATEGORY',
        categoryId: (int) $categoryB->getKey(),
        unitId: (int) $unitA->getKey(),
        taxId: (int) $taxA->getKey(),
    ))->toThrow(QueryException::class);

    expect(fn () => m31DirectProductInsert(
        companyId: (int) $companyA->getKey(),
        code: 'BAD-UNIT',
        categoryId: (int) $categoryA->getKey(),
        unitId: (int) $unitB->getKey(),
        taxId: (int) $taxA->getKey(),
    ))->toThrow(QueryException::class);

    expect(fn () => m31DirectProductInsert(
        companyId: (int) $companyA->getKey(),
        code: 'BAD-TAX',
        categoryId: (int) $categoryA->getKey(),
        unitId: (int) $unitA->getKey(),
        taxId: (int) $taxB->getKey(),
    ))->toThrow(QueryException::class);

    expect(fn () => DB::table('products')->insert([
        'company_id' => $companyA->getKey(),
        'code' => 'BAD-PRICE',
        'status' => ProductStatus::Active->value,
        'name' => 'Bad Price',
        'category_id' => $categoryA->getKey(),
        'unit_id' => $unitA->getKey(),
        'tax_id' => $taxA->getKey(),
        'sale_price_net' => '-0.000001',
        'purchase_price_net' => '0',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    DB::table('barcodes')->insert([
        'company_id' => $companyA->getKey(),
        'product_id' => $validId,
        'barcode' => 'DB-BARCODE',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('barcodes')->insert([
        'company_id' => $companyA->getKey(),
        'product_id' => $validId,
        'barcode' => 'SECOND-PRIMARY',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $companyBProductId = m31DirectProductInsert(
        companyId: (int) $companyB->getKey(),
        code: 'DB-SKU',
        categoryId: (int) $categoryB->getKey(),
        unitId: (int) $unitB->getKey(),
        taxId: (int) $taxB->getKey(),
    );

    DB::table('barcodes')->insert([
        'company_id' => $companyB->getKey(),
        'product_id' => $companyBProductId,
        'barcode' => 'DB-BARCODE',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Barcode::query()->where('barcode', 'DB-BARCODE')->count())->toBe(2);
});

/** @return array{Company, User} */
function m31Context(string $companyCode): array
{
    $company = Company::query()->create([
        'code' => $companyCode,
        'name' => 'Company '.$companyCode,
    ]);
    $actor = User::query()->create([
        'name' => 'M3.1 Product Actor '.$companyCode,
        'email' => strtolower($companyCode).'@m31-products.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);

    app(ActiveCompanyContext::class)->set($company);
    app(CorrelationContext::class)->set('m3-1-product-test');
    test()->actingAs($actor);

    return [$company, $actor];
}

function m31Category(Company $company, string $code, string $name, bool $active = true): Category
{
    return Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'name' => $name,
        'is_active' => $active,
    ]);
}

function m31Unit(Company $company, string $code, string $name, bool $active = true): Unit
{
    return Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'name' => $name,
        'is_active' => $active,
    ]);
}

function m31Tax(Company $company, string $code, string $name, string $rate, bool $active = true): Tax
{
    return Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => $code,
        'name' => $name,
        'rate' => $rate,
        'is_active' => $active,
    ]);
}

/** @return array{Category, Unit, Tax} */
function m31References(Company $company, string $suffix): array
{
    return [
        m31Category($company, 'CAT-'.$suffix, 'Category '.$suffix),
        m31Unit($company, 'UNIT-'.$suffix, 'Unit '.$suffix),
        m31Tax($company, 'TAX-'.$suffix, 'Tax '.$suffix, '20'),
    ];
}

function m31ProductData(
    Category $category,
    Unit $unit,
    Tax $tax,
    string $code,
    ?string $barcode,
): CreateProductData {
    return new CreateProductData(
        code: $code,
        name: 'Product '.$code,
        categoryId: (int) $category->getKey(),
        unitId: (int) $unit->getKey(),
        taxId: (int) $tax->getKey(),
        salePriceNet: '100',
        purchasePriceNet: '60',
        primaryBarcode: $barcode,
    );
}

function m31DirectProductInsert(
    int $companyId,
    string $code,
    int $categoryId,
    int $unitId,
    int $taxId,
): int {
    return (int) DB::table('products')->insertGetId([
        'company_id' => $companyId,
        'code' => $code,
        'status' => ProductStatus::Active->value,
        'name' => 'DB Product '.$code,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'tax_id' => $taxId,
        'sale_price_net' => '100.000000',
        'purchase_price_net' => '60.000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
