<?php

namespace App\Modules\Operations;

use RuntimeException;

final class NotificationDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $failureClass,
        public readonly bool $retryable,
        public readonly bool $ambiguous = false,
        public readonly bool $manualRetryRequired = false,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }
}
