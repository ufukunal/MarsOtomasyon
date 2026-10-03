<?php

namespace Tests\Support;

use App\Models\Period\PriceList;
use App\Models\Period\Product;
use App\Models\Period\Unit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    protected function createStockBalanceSchema(): void
    {
        if (Schema::connection('period')->hasTable('stock_balances')) {
            return;
        }

        Schema::connection('period')->create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('reserved', 18, 3)->default(0);
            $table->decimal('consignment_reserved', 18, 3)->default(0);
            $table->decimal('quarantine', 18, 3)->default(0);
        });
    }

    protected function setAvailableStock(Product $product, string $quantity): void
    {
        $this->createStockBalanceSchema();

        DB::connection('period')->table('stock_balances')->updateOrInsert(
            ['product_id' => $product->id],
            [
                'quantity' => $quantity,
                'reserved' => '0.000',
                'consignment_reserved' => '0.000',
                'quarantine' => '0.000',
            ],
        );
    }
}
