<?php

namespace App\Modules\Products\Pricing;

use InvalidArgumentException;

final class ProductPriceNormalizer
{
    public function normalize(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw new InvalidArgumentException('Ürün fiyatı negatif olamaz ve en fazla 6 ondalık içermelidir.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        if (strlen($whole) > 14) {
            throw new InvalidArgumentException('Ürün fiyatı desteklenen decimal sınırını aşıyor.');
        }

        return $whole.'.'.str_pad($fraction, 6, '0');
    }
}
