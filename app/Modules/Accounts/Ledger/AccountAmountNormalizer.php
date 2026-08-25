<?php

namespace App\Modules\Accounts\Ledger;

use InvalidArgumentException;

final class AccountAmountNormalizer
{
    public function normalize(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^-?\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw new InvalidArgumentException('Cari hareket tutarı en fazla 6 ondalıklı standart decimal olmalıdır.');
        }

        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');

        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 14) {
            throw new InvalidArgumentException('Cari hareket tutarı desteklenen decimal sınırını aşıyor.');
        }

        $fraction = str_pad($fraction, 6, '0');
        if ($whole === '0' && $fraction === '000000') {
            throw new InvalidArgumentException('Cari hareket tutarı sıfır olamaz.');
        }

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    public function negate(string $value): string
    {
        $normalized = $this->normalize($value);

        return str_starts_with($normalized, '-')
            ? substr($normalized, 1)
            : '-'.$normalized;
    }
}
