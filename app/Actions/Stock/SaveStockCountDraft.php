<?php

namespace App\Actions\Stock;

use App\Enums\ProductKind;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\StockCount;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveStockCountDraft
{
    /** @param array{location_id:int,count_date:string,note?:?string,product_ids:list<int>} $data */
    public function handle(array $data, ?StockCount $count = null): StockCount
    {
        MutationAuthorizer::authorize($count ? 'stock_counts.update' : 'stock_counts.create');
        PeriodContext::ensureWritable();
        Location::query()->findOrFail($data['location_id']);
        CarbonImmutable::parse($data['count_date']);

        $productIds = array_values(array_unique(array_map('intval', $data['product_ids'])));

        if ($productIds === []) {
            throw ValidationException::withMessages(['product_ids' => 'Sayım için en az bir ürün seçilmelidir.']);
        }

        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($productIds as $productId) {
            $product = $products->get($productId);
            if (! $product) {
                throw ValidationException::withMessages(['product_ids' => 'Seçilen ürünlerden biri bulunamadı.']);
            }
            if ($product->kind === ProductKind::Set) {
                throw ValidationException::withMessages(['product_ids' => 'Set ürün fiziksel sayım satırı olamaz.']);
            }
        }

        return DB::connection('period')->transaction(function () use ($data, $count, $productIds): StockCount {
            if ($count) {
                $count = StockCount::query()->lockForUpdate()->findOrFail($count->id);
                if ($count->status !== 'draft') {
                    throw new DomainException('Yalnız taslak sayım düzenlenebilir.');
                }
            } else {
                $count = new StockCount;
                $count->status = 'draft';
                $count->created_by = auth()->id();
            }

            $count->location_id = $data['location_id'];
            $count->count_date = $data['count_date'];
            $count->note = $data['note'] ?? null;
            $count->save();
            $count->lines()->delete();

            foreach ($productIds as $productId) {
                $count->lines()->create([
                    'product_id'=>$productId,'system_quantity'=>'0.000','counted_quantity'=>null,
                    'difference'=>'0.000','is_approved'=>false,
                ]);
            }

            return $count->refresh()->load(['location','lines.product']);
        }, attempts: 3);
    }
}
