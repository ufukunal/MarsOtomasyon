<?php

namespace App\Modules\Products\Search;

use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Builder;

final class ProductSearchQuery
{
    /** @return Builder<Product> */
    public function build(int $companyId, string $search = '', ?ProductStatus $status = null): Builder
    {
        $query = Product::query()->where('company_id', $companyId);
        $search = trim($search);

        if ($search !== '') {
            $normalizedSearch = mb_strtolower($search);
            $like = '%'.$normalizedSearch.'%';
            $vector = "to_tsvector('simple', coalesce(products.code, '') || ' ' || coalesce(products.name, '') || ' ' || coalesce(products.brand, ''))";

            $query
                ->select('products.*')
                ->selectRaw(
                    "(CASE WHEN lower(products.code) = ? THEN 8 ELSE 0 END
                    + CASE
                        WHEN EXISTS (
                            SELECT 1 FROM barcodes exact_search_barcodes
                            WHERE exact_search_barcodes.company_id = products.company_id
                              AND exact_search_barcodes.product_id = products.id
                              AND lower(exact_search_barcodes.barcode) = ?
                        ) THEN 10
                        WHEN EXISTS (
                            SELECT 1 FROM barcodes partial_search_barcodes
                            WHERE partial_search_barcodes.company_id = products.company_id
                              AND partial_search_barcodes.product_id = products.id
                              AND lower(partial_search_barcodes.barcode) LIKE ?
                        ) THEN 3
                        ELSE 0
                    END
                    + ts_rank({$vector}, plainto_tsquery('simple', ?)) * 4
                    + GREATEST(
                        similarity(lower(products.name), ?),
                        word_similarity(?, lower(products.name))
                    ) * 3
                    + similarity(lower(products.code), ?) * 2
                    + similarity(lower(coalesce(products.brand, '')), ?) * 2) AS search_score",
                    [
                        $normalizedSearch,
                        $normalizedSearch,
                        $like,
                        $search,
                        $normalizedSearch,
                        $normalizedSearch,
                        $normalizedSearch,
                        $normalizedSearch,
                    ],
                )
                ->where(function (Builder $builder) use ($vector, $search, $normalizedSearch, $like): void {
                    $builder
                        ->whereRaw("{$vector} @@ plainto_tsquery('simple', ?)", [$search])
                        ->orWhereRaw('lower(products.code) = ?', [$normalizedSearch])
                        ->orWhereRaw('lower(products.code) LIKE ?', [$like])
                        ->orWhereRaw('lower(products.name) LIKE ?', [$like])
                        ->orWhereRaw("lower(coalesce(products.brand, '')) LIKE ?", [$like])
                        ->orWhereRaw('similarity(lower(products.code), ?) >= 0.15', [$normalizedSearch])
                        ->orWhereRaw('similarity(lower(products.name), ?) >= 0.15', [$normalizedSearch])
                        ->orWhereRaw("similarity(lower(coalesce(products.brand, '')), ?) >= 0.15", [$normalizedSearch])
                        ->orWhereRaw('word_similarity(?, lower(products.name)) >= 0.35', [$normalizedSearch])
                        ->orWhereHas('barcodes', function (Builder $barcodeQuery) use ($normalizedSearch, $like): void {
                            $barcodeQuery->where(function (Builder $barcodeValueQuery) use ($normalizedSearch, $like): void {
                                $barcodeValueQuery
                                    ->whereRaw('lower(barcodes.barcode) = ?', [$normalizedSearch])
                                    ->orWhereRaw('lower(barcodes.barcode) LIKE ?', [$like]);
                            });
                        })
                        ->orWhereHas('category', function (Builder $categoryQuery) use ($like): void {
                            $categoryQuery->where(function (Builder $categoryValueQuery) use ($like): void {
                                $categoryValueQuery
                                    ->whereRaw('lower(categories.code) LIKE ?', [$like])
                                    ->orWhereRaw('lower(categories.name) LIKE ?', [$like]);
                            });
                        });
                })
                ->orderByDesc('search_score');
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query;
    }
}
