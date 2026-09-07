<?php

use App\Modules\UpdateCenter\UpdateRunState;
use DomainException;

it('allows only the forward update lifecycle', function (): void {
    UpdateRunState::Requested->assertCanTransitionTo(UpdateRunState::Verified);
    UpdateRunState::Verified->assertCanTransitionTo(UpdateRunState::Staged);
    UpdateRunState::Staged->assertCanTransitionTo(UpdateRunState::Applying);
    UpdateRunState::Applying->assertCanTransitionTo(UpdateRunState::HealthCheck);
    UpdateRunState::HealthCheck->assertCanTransitionTo(UpdateRunState::Activated);

    expect(UpdateRunState::Activated->isTerminal())->toBeTrue();
});

it('fails closed for skipped or reverse transitions', function (): void {
    expect(fn () => UpdateRunState::Requested->assertCanTransitionTo(UpdateRunState::Applying))
        ->toThrow(DomainException::class);

    expect(fn () => UpdateRunState::Activated->assertCanTransitionTo(UpdateRunState::Verified))
        ->toThrow(DomainException::class);
});

it('supports rollback only after application starts', function (): void {
    UpdateRunState::Applying->assertCanTransitionTo(UpdateRunState::RollbackRequested);
    UpdateRunState::HealthCheck->assertCanTransitionTo(UpdateRunState::RollbackRequested);
    UpdateRunState::RollbackRequested->assertCanTransitionTo(UpdateRunState::RolledBack);

    expect(fn () => UpdateRunState::Verified->assertCanTransitionTo(UpdateRunState::RollbackRequested))
        ->toThrow(DomainException::class);
});
