<?php

namespace App\Modules\Quotes\Pricing;

use InvalidArgumentException;
use LogicException;

final readonly class Decimal6
{
    private const SCALE = 6;

    private const SCALE_FACTOR = '1000000';

    private const PERCENT_FACTOR = '100000000';

    private const MAX_WHOLE_DIGITS = 14;

    private function __construct(private string $units) {}

    public static function zero(): self
    {
        return new self('0');
    }

    public static function nonNegative(string $value, string $field): self
    {
        return new self(self::parseUnits($value, $field, false));
    }

    public static function positive(string $value, string $field): self
    {
        return new self(self::parseUnits($value, $field, true));
    }

    public static function rate(string $value, string $field): self
    {
        $rate = self::nonNegative($value, $field);

        if (self::compareBig($rate->units, self::PERCENT_FACTOR) > 0) {
            throw new InvalidArgumentException($field.' yüzde 100 değerini aşamaz.');
        }

        return $rate;
    }

    public function value(): string
    {
        return self::formatUnits($this->units);
    }

    public function isZero(): bool
    {
        return $this->units === '0';
    }

    public function add(self $other): self
    {
        return self::fromUnits(self::addBig($this->units, $other->units));
    }

    public function subtract(self $other, string $field = 'value'): self
    {
        if (self::compareBig($this->units, $other->units) < 0) {
            throw new InvalidArgumentException($field.' negatif sonuca düşemez.');
        }

        return self::fromUnits(self::subtractBig($this->units, $other->units));
    }

    public function multiply(self $other): self
    {
        return self::fromUnits(
            self::roundDivide(
                self::multiplyBig($this->units, $other->units),
                self::SCALE_FACTOR,
            ),
        );
    }

    public function percent(self $rate): self
    {
        return self::fromUnits(
            self::roundDivide(
                self::multiplyBig($this->units, $rate->units),
                self::PERCENT_FACTOR,
            ),
        );
    }

    public function netFromGross(self $taxRate): self
    {
        $denominator = self::addBig(self::PERCENT_FACTOR, $taxRate->units);

        return self::fromUnits(
            self::roundDivide(
                self::multiplyBig($this->units, self::PERCENT_FACTOR),
                $denominator,
            ),
        );
    }

    private static function fromUnits(string $units): self
    {
        self::formatUnits($units);

        return new self(self::normalizeBig($units));
    }

    private static function parseUnits(string $value, string $field, bool $positive): string
    {
        $value = trim($value);

        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw new InvalidArgumentException($field.' negatif olamaz ve en fazla 6 ondalık içermelidir.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        if (strlen($whole) > self::MAX_WHOLE_DIGITS) {
            throw new InvalidArgumentException($field.' desteklenen NUMERIC(20,6) sınırını aşıyor.');
        }

        $units = self::normalizeBig($whole.str_pad($fraction, self::SCALE, '0'));

        if ($positive && $units === '0') {
            throw new InvalidArgumentException($field.' sıfırdan büyük olmalıdır.');
        }

        return $units;
    }

    private static function formatUnits(string $units): string
    {
        $units = self::normalizeBig($units);
        $padded = str_pad($units, self::SCALE + 1, '0', STR_PAD_LEFT);
        $whole = substr($padded, 0, -self::SCALE);
        $fraction = substr($padded, -self::SCALE);

        if (strlen($whole) > self::MAX_WHOLE_DIGITS) {
            throw new InvalidArgumentException('Hesap sonucu desteklenen NUMERIC(20,6) sınırını aşıyor.');
        }

        return $whole.'.'.$fraction;
    }

    private static function roundDivide(string $numerator, string $denominator): string
    {
        $denominator = self::normalizeBig($denominator);

        if ($denominator === '0') {
            throw new LogicException('Decimal division by zero.');
        }

        [$quotient, $remainder] = self::divideBig($numerator, $denominator);

        if (self::compareBig(self::multiplySmall($remainder, 2), $denominator) >= 0) {
            $quotient = self::addBig($quotient, '1');
        }

        return $quotient;
    }

    /**
     * @return array{string, string}
     */
    private static function divideBig(string $numerator, string $denominator): array
    {
        $numerator = self::normalizeBig($numerator);
        $denominator = self::normalizeBig($denominator);
        $quotient = '';
        $remainder = '0';

        foreach (str_split($numerator) as $digit) {
            $remainder = self::normalizeBig(($remainder === '0' ? '' : $remainder).$digit);
            $quotientDigit = 0;

            for ($candidate = 9; $candidate >= 1; $candidate--) {
                $candidateValue = self::multiplySmall($denominator, $candidate);

                if (self::compareBig($candidateValue, $remainder) <= 0) {
                    $quotientDigit = $candidate;
                    $remainder = self::subtractBig($remainder, $candidateValue);

                    break;
                }
            }

            $quotient .= (string) $quotientDigit;
        }

        return [self::normalizeBig($quotient), self::normalizeBig($remainder)];
    }

    private static function multiplyBig(string $left, string $right): string
    {
        $left = self::normalizeBig($left);
        $right = self::normalizeBig($right);

        if ($left === '0' || $right === '0') {
            return '0';
        }

        $leftDigits = array_reverse(array_map('intval', str_split($left)));
        $rightDigits = array_reverse(array_map('intval', str_split($right)));
        $result = array_fill(0, count($leftDigits) + count($rightDigits), 0);

        foreach ($leftDigits as $leftIndex => $leftDigit) {
            foreach ($rightDigits as $rightIndex => $rightDigit) {
                $result[$leftIndex + $rightIndex] += $leftDigit * $rightDigit;
            }
        }

        for ($index = 0, $limit = count($result) - 1; $index < $limit; $index++) {
            if ($result[$index] < 10) {
                continue;
            }

            $result[$index + 1] += intdiv($result[$index], 10);
            $result[$index] %= 10;
        }

        while (count($result) > 1 && end($result) === 0) {
            array_pop($result);
        }

        return implode('', array_reverse($result));
    }

    private static function multiplySmall(string $value, int $multiplier): string
    {
        if ($multiplier < 0 || $multiplier > 9) {
            throw new LogicException('Small multiplier must be between 0 and 9.');
        }

        $value = self::normalizeBig($value);

        if ($value === '0' || $multiplier === 0) {
            return '0';
        }

        $carry = 0;
        $result = '';

        for ($index = strlen($value) - 1; $index >= 0; $index--) {
            $product = ((int) $value[$index] * $multiplier) + $carry;
            $result = (string) ($product % 10).$result;
            $carry = intdiv($product, 10);
        }

        if ($carry > 0) {
            $result = (string) $carry.$result;
        }

        return self::normalizeBig($result);
    }

    private static function addBig(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $carry = 0;
        $result = '';

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $sum = $carry;

            if ($leftIndex >= 0) {
                $sum += (int) $left[$leftIndex--];
            }

            if ($rightIndex >= 0) {
                $sum += (int) $right[$rightIndex--];
            }

            $result = (string) ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return self::normalizeBig($result);
    }

    private static function subtractBig(string $left, string $right): string
    {
        if (self::compareBig($left, $right) < 0) {
            throw new LogicException('Unsigned decimal subtraction underflow.');
        }

        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $borrow = 0;
        $result = '';

        while ($leftIndex >= 0) {
            $digit = (int) $left[$leftIndex] - $borrow - ($rightIndex >= 0 ? (int) $right[$rightIndex] : 0);
            $borrow = 0;

            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            }

            $result = (string) $digit.$result;
            $leftIndex--;
            $rightIndex--;
        }

        return self::normalizeBig($result);
    }

    private static function compareBig(string $left, string $right): int
    {
        $left = self::normalizeBig($left);
        $right = self::normalizeBig($right);

        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return strcmp($left, $right) <=> 0;
    }

    private static function normalizeBig(string $value): string
    {
        $value = ltrim($value, '0');

        return $value === '' ? '0' : $value;
    }
}
