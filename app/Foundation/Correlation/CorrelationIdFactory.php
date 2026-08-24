<?php

namespace App\Foundation\Correlation;

use Illuminate\Support\Str;

final class CorrelationIdFactory
{
    public const MAX_LENGTH = 64;

    public function resolve(?string $candidate): string
    {
        if ($candidate !== null && $this->isValid($candidate)) {
            return $candidate;
        }

        return (string) Str::ulid();
    }

    public function isValid(string $candidate): bool
    {
        if ($candidate === '' || strlen($candidate) > self::MAX_LENGTH) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $candidate) === 1;
    }
}
