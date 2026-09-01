<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Labels\LabelRenderService;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('renders existing product Barcode identity to ZPL and PDF with reprint audit', function (): void {
    $company = m26LabelCompany('M26-LABEL');
    $product = m26LabelProduct($company, 'LBL-001');
    $barcode = Barcode::query()->create([
        'company_id' => $company->getKey(),
        'product_id' => $product->getKey(),
        'barcode' => '8690000000001',
        'is_primary' => true,
    ]);
    $service = app(LabelRenderService::class);
    $zpl = $service->createTemplate(
        (int) $company->getKey(),
        'product-zpl',
        'Product ZPL',
        'zpl',
        '^XA^FO20,20^FD{{product.code}} {{barcode}}^FS^XZ',
        100,
        50,
    );

    $first = $service->renderProduct((int) $company->getKey(), (int) $product->getKey(), (int) $zpl->getKey());
    expect($first->content)->toBe('^XA^FO20,20^FDLBL-001 8690000000001^FS^XZ');
    expect($first->sha256)->toBe(hash('sha256', $first->content));

    $reprint = $service->renderProduct((int) $company->getKey(), (int) $product->getKey(), (int) $zpl->getKey(), $first->renderRequestId);
    expect($reprint->content)->toBe($first->content);
    expect((int) DB::table('label_render_requests')->where('id', $reprint->renderRequestId)->value('reprint_of_id'))
        ->toBe($first->renderRequestId);
    expect($barcode->fresh()->barcode)->toBe('8690000000001');

    $pdf = $service->createTemplate(
        (int) $company->getKey(),
        'product-pdf',
        'Product PDF',
        'pdf',
        '<html><body><strong>{{product.name}}</strong><div>{{barcode}}</div></body></html>',
        80,
        40,
    );
    $pdfResult = $service->renderProduct((int) $company->getKey(), (int) $product->getKey(), (int) $pdf->getKey());
    expect(str_starts_with($pdfResult->content, '%PDF'))->toBeTrue();
    expect($pdfResult->mimeType)->toBe('application/pdf');
});

it('cannot render a product label across company boundaries', function (): void {
    $companyA = m26LabelCompany('M26-A');
    $companyB = m26LabelCompany('M26-B');
    $product = m26LabelProduct($companyA, 'LBL-A');
    Barcode::query()->create([
        'company_id' => $companyA->getKey(),
        'product_id' => $product->getKey(),
        'barcode' => '8690000000002',
        'is_primary' => true,
    ]);
    $service = app(LabelRenderService::class);
    $template = $service->createTemplate((int) $companyB->getKey(), 'other-company', 'Other Company', 'zpl', '^XA^FD{{barcode}}^FS^XZ');

    expect(fn () => $service->renderProduct((int) $companyB->getKey(), (int) $product->getKey(), (int) $template->getKey()))
        ->toThrow(DomainException::class, 'not found for company');
});

function m26LabelCompany(string $code): Company
{
    return Company::query()->create([
        'code' => $code,
        'name' => 'Company '.$code,
        'status' => 'active',
        'base_currency_code' => 'TRY',
        'timezone' => 'Europe/Istanbul',
    ]);
}

function m26LabelProduct(Company $company, string $code): Product
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
