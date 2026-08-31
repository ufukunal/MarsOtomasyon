<?php

namespace App\Modules\Operations;

use RuntimeException;
use Throwable;

final class NotificationDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $failureClass,
        public readonly bool $retryable,
        public readonly bool $ambiguous = false,
        public readonly bool $manualRetryRequired = false,
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
