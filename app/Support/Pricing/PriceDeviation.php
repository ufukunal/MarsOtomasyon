<?php

namespace App\Support\Pricing;

use App\Models\Period\Product;
use App\Support\Audit\AuditContext;

final class PriceDeviation
{
    public function warnIfNeeded(Product $product, string $resolvedPrice, string $enteredPrice): ?string
    {
        if (bccomp($resolvedPrice, '0', 4) <= 0) {
            return null;
        }

        $difference = bcsub($enteredPrice, $resolvedPrice, 4);

        if (bccomp($difference, '0', 4) < 0) {
            $difference = bcmul($difference, '-1', 4);
        }

        $percent = bcmul(
            bcdiv($difference, $resolvedPrice, 8),
            '100',
            4,
        );

        if (bccomp($percent, '20', 4) < 0) {
            return null;
        }

        AuditContext::period(
            'Satış fiyatı referans fiyattan %20 veya daha fazla sapıyor.',
            [
                'product_id' => $product->id,
                'resolved_price' => $resolvedPrice,
                'entered_price' => $enteredPrice,
                'deviation_percent' => $percent,
            ],
            $product,
            'price_deviation_warning',
        );

        return "Fiyat sapması %{$percent}. İşlem engellenmedi.";
    }
}
