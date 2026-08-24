<?php

namespace App\Foundation\Correlation;

use LogicException;

final class CorrelationContext
{
    private ?string $id = null;

    public function set(string $id): void
    {
        $this->id = $id;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function requireId(): string
    {
        return $this->id ?? throw new LogicException('Correlation ID has not been initialized.');
    }
}
