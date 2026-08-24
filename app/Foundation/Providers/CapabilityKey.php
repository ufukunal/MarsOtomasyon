<?php

namespace App\Foundation\Providers;

use InvalidArgumentException;
use Stringable;

final readonly class CapabilityKey implements Stringable
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $value)) {
            throw new InvalidArgumentException('Capability key must be canonical lowercase ASCII.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
