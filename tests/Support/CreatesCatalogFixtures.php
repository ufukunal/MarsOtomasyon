<?php

namespace Tests\Support;

use App\Models\Period\Location;
use App\Models\Period\PriceList;
use App\Models\Period\Product;
use App\Models\Period\Unit;
use Illuminate\Support\Facades\DB;

trait CreatesCatalogFixtures
{
    private int $testProductSequence = 0;

    protected function createTestProduct(array $attributes = []): Product
    {
        $this->testProductSequence++;
        $unit = Unit::query()->where('code', 'ADET')->firstOrFail();

        return Product::query()->create(array_merge([
            'code' => 'P-'.str_pad((string) $this->testProductSequence, 5, '0', STR_PAD_LEFT),
            'name' => 'Test Ürün '.$this->testProductSequence,
            'unit_id' => $unit->id,
            'barcode' => null,
            'vat_rate' => '20.0000',
            'list_price' => '100.0000',
            'currency' => 'TRY',
            'kind' => 'normal',
            'allow_negative_stock' => false,
            'min_stock' => '0.000',
            'channel_stock_mode' => 'stock',
            'is_active' => true,
        ], $attributes));
    }

    protected function createTestPriceList(array $attributes = []): PriceList
    {
        return PriceList::query()->create(array_merge([
            'name' => 'Test Fiyat Listesi',
            'currency' => 'TRY',
            'vat_included' => false,
            'is_default' => false,
            'is_active' => true,
        ], $attributes));
    }

    protected function setAvailableStock(Product $product, string $quantity): void
    {
        $location = Location::query()->firstOrCreate(
            ['code' => 'TEST-STOCK'],
            [
                'name' => 'Test Stok Lokasyonu',
                'kind' => 'warehouse',
                'is_default' => false,
                'is_active' => true,
            ],
        );

        DB::connection('period')->table('stock_balances')->updateOrInsert(
            [
                'product_id' => $product->id,
                'location_id' => $location->id,
            ],
            [
                'quantity' => $quantity,
                'reserved' => '0.000',
                'consignment_reserved' => '0.000',
                'quarantine' => '0.000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
