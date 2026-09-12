<?php

use App\Modules\UpdateCenter\UpdateRunState;
use DomainException;

it('allows the complete forward update lifecycle', function (): void {
    UpdateRunState::Requested->assertCanTransitionTo(UpdateRunState::Verified);
    UpdateRunState::Verified->assertCanTransitionTo(UpdateRunState::Staging);
    UpdateRunState::Staging->assertCanTransitionTo(UpdateRunState::Staged);
    UpdateRunState::Staged->assertCanTransitionTo(UpdateRunState::ApplyRequested);
    UpdateRunState::ApplyRequested->assertCanTransitionTo(UpdateRunState::BackupCreating);
    UpdateRunState::BackupCreating->assertCanTransitionTo(UpdateRunState::BackupCreated);
    UpdateRunState::BackupCreated->assertCanTransitionTo(UpdateRunState::Maintenance);
    UpdateRunState::Maintenance->assertCanTransitionTo(UpdateRunState::Migrating);
    UpdateRunState::Migrating->assertCanTransitionTo(UpdateRunState::Activating);
    UpdateRunState::Activating->assertCanTransitionTo(UpdateRunState::HealthChecking);
    UpdateRunState::HealthChecking->assertCanTransitionTo(UpdateRunState::Completed);

    expect(UpdateRunState::Completed->isFinished())->toBeTrue();
});

it('fails closed for skipped reverse and post-failure transitions', function (): void {
    expect(fn () => UpdateRunState::Requested->assertCanTransitionTo(UpdateRunState::Activating))
        ->toThrow(DomainException::class);
    expect(fn () => UpdateRunState::Migrating->assertCanTransitionTo(UpdateRunState::Staged))
        ->toThrow(DomainException::class);
    expect(fn () => UpdateRunState::Failed->assertCanTransitionTo(UpdateRunState::Verified))
        ->toThrow(DomainException::class);
});

it('permits rollback once a verified backup exists and after completion', function (): void {
    UpdateRunState::BackupCreated->assertCanTransitionTo(UpdateRunState::RollbackRequested);
    UpdateRunState::Migrating->assertCanTransitionTo(UpdateRunState::RollbackRequested);
    UpdateRunState::HealthChecking->assertCanTransitionTo(UpdateRunState::RollbackRequested);
    UpdateRunState::Completed->assertCanTransitionTo(UpdateRunState::RollbackRequested);
    UpdateRunState::RollbackRequested->assertCanTransitionTo(UpdateRunState::RollingBack);
    UpdateRunState::RollingBack->assertCanTransitionTo(UpdateRunState::RolledBack);

    expect(UpdateRunState::RolledBack->isFinished())->toBeTrue();
});
