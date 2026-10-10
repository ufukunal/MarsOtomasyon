<?php

namespace App\Support\Operations;

use RuntimeException;

/**
 * Rejects activation whenever the operational health check is not fully green.
 * Keeps the status decision independently testable without PROD services.
 */
final class ReleaseActivationGate
{
    public static function assertHealthy(string $status, string $correlationId): void
    {
        if ($status !== 'healthy') {
            throw new RuntimeException(
                "Candidate release aktivasyonu engellendi: pre-activation health={$status}, correlation_id={$correlationId}.",
            );
        }
    }
}
