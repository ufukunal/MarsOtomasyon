<?php

namespace App\Modules\UpdateCenter;

use DomainException;

enum UpdateRunState: string
{
    case Requested = 'requested';
    case Verified = 'verified';
    case Staging = 'staging';
    case Staged = 'staged';
    case ApplyRequested = 'apply_requested';
    case BackupCreating = 'backup_creating';
    case BackupCreated = 'backup_created';
    case Maintenance = 'maintenance';
    case Migrating = 'migrating';
    case Activating = 'activating';
    case HealthChecking = 'health_checking';
    case Completed = 'completed';
    case RollbackRequested = 'rollback_requested';
    case RollingBack = 'rolling_back';
    case RolledBack = 'rolled_back';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::RolledBack, self::Failed], true);
    }

    public function assertCanTransitionTo(self $next): void
    {
        $allowed = match ($this) {
            self::Requested => [self::Verified, self::Failed],
            self::Verified => [self::Staging, self::Failed],
            self::Staging => [self::Staged, self::Failed],
            self::Staged => [self::ApplyRequested, self::Failed],
            self::ApplyRequested => [self::BackupCreating, self::Failed],
            self::BackupCreating => [self::BackupCreated, self::Failed],
            self::BackupCreated => [self::Maintenance, self::RollbackRequested, self::Failed],
            self::Maintenance => [self::Migrating, self::RollbackRequested, self::Failed],
            self::Migrating => [self::Activating, self::RollbackRequested, self::Failed],
            self::Activating => [self::HealthChecking, self::RollbackRequested, self::Failed],
            self::HealthChecking => [self::Completed, self::RollbackRequested, self::Failed],
            self::Completed => [self::RollbackRequested],
            self::RollbackRequested => [self::RollingBack, self::Failed],
            self::RollingBack => [self::RolledBack, self::Failed],
            self::RolledBack, self::Failed => [],
        };

        if (! in_array($next, $allowed, true)) {
            throw new DomainException("Invalid update run transition: {$this->value} -> {$next->value}.");
        }
    }
}
