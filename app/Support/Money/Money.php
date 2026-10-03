<?php

namespace App\Support\Money;

use DomainException;

final readonly class Money
{
    private function __construct(
        public string $amount,
        public string $currency,
    ) {
    }

    public static function of(string|int $amount, string $currency = 'TRY'): self
    {
        return new self(bcadd((string) $amount, '0', 4), $currency);
    }

    public function plus(Money $other): self
    {
        $this->assertSame($other);

        return new self(
            bcadd($this->amount, $other->amount, 4),
            $this->currency,
        );
    }

    public function minus(Money $other): self
    {
        $this->assertSame($other);

        return new self(
            bcsub($this->amount, $other->amount, 4),
            $this->currency,
        );
    }

    public function times(string $multiplier): self
    {
        return new self(
            bcmul($this->amount, $multiplier, 4),
            $this->currency,
        );
    }

    public function percent(string $rate): self
    {
        return new self(
            bcdiv(bcmul($this->amount, $rate, 6), '100', 4),
            $this->currency,
        );
    }

    public function round(int $scale = 2): self
    {
        $factor = bcpow('10', (string) $scale);
        $offset = bccomp($this->amount, '0', 4) < 0 ? '-0.5' : '0.5';

        $value = bcdiv(
            bcadd(
                bcmul($this->amount, $factor, 4),
                $offset,
                4,
            ),
            $factor,
            $scale,
        );

        return new self(
            bcadd($value, '0', 4),
            $this->currency,
        );
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', 4) < 0;
    }

    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency
            && bccomp($this->amount, $other->amount, 4) === 0;
    }

    public function __toString(): string
    {
        return $this->amount;
    }

    private function assertSame(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new DomainException(
                "Farklı para birimleri toplanamaz: {$this->currency} + {$other->currency}",
            );
        }
    }
}
