<?php

use App\Modules\UpdateCenter\UpdateRunState;
use DomainException;

it('allows only the declared update lifecycle transitions', function (): void {
    UpdateRunState::Requested->assertCanTransitionTo(UpdateRunState::Verified);
    UpdateRunState::Verified->assertCanTransitionTo(UpdateRunState::Staged);
    UpdateRunState::Staged->assertCanTransitionTo(UpdateRunState::Applying);
    UpdateRunState::Applying->assertCanTransitionTo(UpdateRunState::HealthCheck);
    UpdateRunState::HealthCheck->assertCanTransitionTo(UpdateRunState::Activated);

    expect(UpdateRunState::Activated->isTerminal())->toBeTrue()
        ->and(UpdateRunState::RolledBack->isTerminal())->toBeTrue()
        ->and(UpdateRunState::Failed->isTerminal())->toBeTrue()
        ->and(UpdateRunState::Applying->isTerminal())->toBeFalse();
});

it('rejects invalid and terminal transitions', function (UpdateRunState $from, UpdateRunState $to): void {
    expect(fn () => $from->assertCanTransitionTo($to))->toThrow(DomainException::class);
})->with([
    [UpdateRunState::Requested, UpdateRunState::Applying],
    [UpdateRunState::Verified, UpdateRunState::Activated],
    [UpdateRunState::Staged, UpdateRunState::RolledBack],
    [UpdateRunState::Activated, UpdateRunState::Failed],
    [UpdateRunState::RolledBack, UpdateRunState::Requested],
    [UpdateRunState::Failed, UpdateRunState::Requested],
]);

it('supports rollback and failure edges', function (): void {
    UpdateRunState::Applying->assertCanTransitionTo(UpdateRunState::RollbackRequested);
    UpdateRunState::HealthCheck->assertCanTransitionTo(UpdateRunState::RollbackRequested);
    UpdateRunState::RollbackRequested->assertCanTransitionTo(UpdateRunState::RolledBack);
    UpdateRunState::Requested->assertCanTransitionTo(UpdateRunState::Failed);
    UpdateRunState::Verified->assertCanTransitionTo(UpdateRunState::Failed);
    UpdateRunState::Staged->assertCanTransitionTo(UpdateRunState::Failed);
    UpdateRunState::Applying->assertCanTransitionTo(UpdateRunState::Failed);
    UpdateRunState::HealthCheck->assertCanTransitionTo(UpdateRunState::Failed);
    UpdateRunState::RollbackRequested->assertCanTransitionTo(UpdateRunState::Failed);

    expect(true)->toBeTrue();
});
