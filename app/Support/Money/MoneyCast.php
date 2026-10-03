<?php

namespace App\Support\Money;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<Money, Money|string|int>
 */
class MoneyCast implements CastsAttributes
{
    public function __construct(
        private readonly ?string $currencyAttribute = 'currency',
    ) {
    }

    public function get(
        Model $model,
        string $key,
        mixed $value,
        array $attributes,
    ): ?Money {
        if ($value === null) {
            return null;
        }

        $currency = $this->currencyAttribute
            ? (string) ($attributes[$this->currencyAttribute] ?? 'TRY')
            : 'TRY';

        return Money::of((string) $value, $currency);
    }

    public function set(
        Model $model,
        string $key,
        mixed $value,
        array $attributes,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            if ($this->currencyAttribute) {
                $currency = (string) ($attributes[$this->currencyAttribute] ?? $value->currency);

                if ($currency !== $value->currency) {
                    throw new InvalidArgumentException(
                        "Money currency {$value->currency}, model currency {$currency} ile eşleşmiyor.",
                    );
                }
            }

            return $value->amount;
        }

        if (is_string($value) || is_int($value)) {
            return Money::of($value)->amount;
        }

        throw new InvalidArgumentException('MoneyCast yalnız Money|string|int kabul eder.');
    }
}
