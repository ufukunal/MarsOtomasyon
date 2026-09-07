<?php

namespace App\Modules\UpdateCenter;

use DomainException;

enum UpdateRunState: string
{
    case Requested = 'requested';
    case Verified = 'verified';
    case Staged = 'staged';
    case Applying = 'applying';
    case HealthCheck = 'health_check';
    case Activated = 'activated';
    case RollbackRequested = 'rollback_requested';
    case RolledBack = 'rolled_back';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Activated, self::RolledBack, self::Failed], true);
    }

    public function assertCanTransitionTo(self $next): void
    {
        $allowed = match ($this) {
            self::Requested => [self::Verified, self::Failed],
            self::Verified => [self::Staged, self::Failed],
            self::Staged => [self::Applying, self::Failed],
            self::Applying => [self::HealthCheck, self::RollbackRequested, self::Failed],
            self::HealthCheck => [self::Activated, self::RollbackRequested, self::Failed],
            self::RollbackRequested => [self::RolledBack, self::Failed],
            self::Activated, self::RolledBack, self::Failed => [],
        };

        if (! in_array($next, $allowed, true)) {
            throw new DomainException("Invalid update run transition: {$this->value} -> {$next->value}.");
        }
    }
}
