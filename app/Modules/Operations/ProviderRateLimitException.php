<?php

namespace App\Modules\Operations;

use RuntimeException;

final class ProviderRateLimitException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $retryAfterSeconds,
    ) {
        parent::__construct($message);
    }
}
