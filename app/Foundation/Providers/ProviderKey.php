<?php

namespace App\Foundation\Providers;

use InvalidArgumentException;
use Stringable;

final readonly class ProviderKey implements Stringable
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $value)) {
            throw new InvalidArgumentException('Provider key must be canonical lowercase ASCII.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
