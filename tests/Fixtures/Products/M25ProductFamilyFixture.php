<?php

namespace Tests\Fixtures\Products;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;

final class M25ProductFamilyFixture
{
    public static function company(string $code): Company
    {
        return Company::query()->create([
            'code' => $code,
            'name' => 'Company '.$code,
            'status' => 'active',
            'base_currency_code' => 'TRY',
            'timezone' => 'Europe/Istanbul',
        ]);
    }

    public static function product(Company $company, string $code): Product
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
}
