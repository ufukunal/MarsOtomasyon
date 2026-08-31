<?php

namespace App\Modules\B2B\Portal;

use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Models\Product;
use App\Modules\Quotes\Pricing\Decimal6;

final class B2BPriceCalculator
{
    public function netPrice(Product $product, Account $account): string
    {
        $base = Decimal6::nonNegative((string) $product->sale_price_net, 'sale_price_net');
        $discount = Decimal6::rate((string) $account->discount_rate, 'discount_rate');

        return $base->subtract($base->percent($discount), 'b2b_price')->value();
    }
}
