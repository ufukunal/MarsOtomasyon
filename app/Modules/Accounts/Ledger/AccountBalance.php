<?php

namespace App\Modules\Accounts\Ledger;

use InvalidArgumentException;

final readonly class AccountBalance
{
    public string $signedAmount;

    public function __construct(string $signedAmount, public string $currencyCode)
    {
        $this->signedAmount = self::normalize($signedAmount);

        if (preg_match('/^[A-Z]{3}$/D', $currencyCode) !== 1) {
            throw new InvalidArgumentException('Cari bakiye para birimi geçersizdir.');
        }
    }

    public function state(): AccountBalanceState
    {
        if ($this->isZero()) {
            return AccountBalanceState::Zero;
        }

        return str_starts_with($this->signedAmount, '-')
            ? AccountBalanceState::Creditor
            : AccountBalanceState::Debtor;
    }

    public function formattedAbsolute(): string
    {
        $absolute = str_starts_with($this->signedAmount, '-')
            ? substr($this->signedAmount, 1)
            : $this->signedAmount;

        [$whole, $fraction] = explode('.', $absolute, 2);
        $groups = [];
        while (strlen($whole) > 3) {
            array_unshift($groups, substr($whole, -3));
            $whole = substr($whole, 0, -3);
        }
        array_unshift($groups, $whole);

        $fraction = rtrim($fraction, '0');
        $fraction = str_pad($fraction, 2, '0');

        return implode('.', $groups).','.$fraction;
    }

    public function formatted(): string
    {
        return $this->formattedAbsolute().' '.$this->currencyCode;
    }

    public function debitDisplay(): string
    {
        return $this->state() === AccountBalanceState::Debtor
            ? $this->formattedAbsolute()
            : '—';
    }

    public function creditDisplay(): string
    {
        return $this->state() === AccountBalanceState::Creditor
            ? $this->formattedAbsolute()
            : '—';
    }

    private function isZero(): bool
    {
        return $this->signedAmount === '0.000000';
    }

    private static function normalize(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^-?\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw new InvalidArgumentException('Cari bakiye standart decimal olmalıdır.');
        }

        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');

        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($fraction, 6, '0');

        if ($whole === '0' && $fraction === '000000') {
            return '0.000000';
        }

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }
}
