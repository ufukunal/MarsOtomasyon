<?php

namespace App\Modules\B2B\Portal;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final readonly class B2BProductVisibility
{
    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function apply(Builder $query, int $companyId, int $accountId): Builder
    {
        return $query->whereNotExists(function (QueryBuilder $override) use ($companyId, $accountId): void {
            $override
                ->selectRaw('1')
                ->from('account_b2b_product_visibilities as b2b_visibility')
                ->whereColumn('b2b_visibility.product_id', 'products.id')
                ->where('b2b_visibility.company_id', $companyId)
                ->where('b2b_visibility.account_id', $accountId)
                ->where('b2b_visibility.is_visible', false);
        });
    }
}
