<?php

namespace App\Modules\UpdateCenter;

use App\Foundation\Operations\ProductionCandidateGate;
use App\Foundation\Operations\ProductionSafetyState;
use App\Modules\Operations\BackupManager;
use App\Modules\Operations\OperationsHealth;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

final class UpdateAgentCoordinator
{
    public function __construct(
        private readonly UpdateRunStore $runs,
        private readonly BackupManager $backups,
        private readonly ProductionSafetyState $safety,
        private readonly ProductionCandidateGate $productionGate,
        private readonly OperationsHealth $health,
    ) {}

    /** @return array{action:string,run_id:int}|null */
    public function claim(): ?array
    {
        return DB::transaction(function (): ?array {
            $rollback = DB::table('update_runs')
                ->where('status', UpdateRunState::RollbackRequested->value)
                ->orderBy('id')
                ->lock('for update skip locked')
                ->first();
            if ($rollback !== null) {
                $this->runs->transition((int) $rollback->id, UpdateRunState::RollingBack, [
                    'agent_claimed_at' => now()->toIso8601String(),
                ]);

                return ['action' => 'rollback', 'run_id' => (int) $rollback->id];
            }

            $apply = DB::table('update_runs')
                ->where('status', UpdateRunState::ApplyRequested->value)
                ->orderBy('id')
                ->lock('for update skip locked')
                ->first();
            if ($apply === null) {
                return null;
            }

            $this->runs->transition((int) $apply->id, UpdateRunState::BackupCreating, [
                'agent_claimed_at' => now()->toIso8601String(),
            ]);

            return ['action' => 'apply', 'run_id' => (int) $apply->id];
        });
    }

    /** @return array<string, int|string> */
    public function artifact(int $runId): array
    {
        $run = $this->runs->find($runId);
        $artifact = $this->runs->artifact($runId);

        return [
            'run_id' => $runId,
            'target_version' => (string) $run->target_version,
            'package_sha256' => (string) $artifact->package_sha256,
            'package_path' => (string) $artifact->package_path,
            'release_path' => (string) $artifact->release_path,
            'package_size_bytes' => (int) $artifact->package_size_bytes,
        ];
    }

    public function createBackup(int $runId): string
    {
        $run = $this->assertState($runId, UpdateRunState::BackupCreating);
        $userId = $run->requested_by_user_id === null ? null : (int) $run->requested_by_user_id;
        $backupId = $this->backups->create($userId);
        if (! $this->backups->verify($backupId)) {
            throw new RuntimeException('Pre-update backup verification failed.');
        }

        $this->runs->transition($runId, UpdateRunState::BackupCreated, [
            'backup_id' => $backupId,
            'backup_verified_at' => now()->toIso8601String(),
        ]);

        return $backupId;
    }

    public function enterMaintenance(int $runId): void
    {
        $this->assertState($runId, UpdateRunState::BackupCreated);
        $issues = $this->productionGate->issues();
        if (app()->environment('production') && $issues !== []) {
            throw new RuntimeException('Production candidate gate blocks update apply: '.implode(', ', $issues));
        }

        $health = $this->health->snapshot();
        if (($health['database_ok'] ?? false) !== true || ($health['valkey_ok'] ?? false) !== true) {
            throw new RuntimeException('Database and Valkey must be healthy before update apply.');
        }
        if ($this->safety->recoveryMode()) {
            throw new RuntimeException('Recovery mode is already active; resolve the existing recovery event first.');
        }

        $this->safety->enterRecoveryMode();
        try {
            $this->runs->transition($runId, UpdateRunState::Maintenance, [
                'maintenance_started_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $exception) {
            $this->safety->leaveRecoveryMode();
            throw $exception;
        }
    }

    public function transition(int $runId, UpdateRunState $state): stdClass
    {
        return $this->runs->transition($runId, $state, [
            'agent_state_at' => now()->toIso8601String(),
        ]);
    }

    public function beginRollback(int $runId): void
    {
        $run = $this->runs->find($runId);
        $current = UpdateRunState::from((string) $run->status);
        if ($current === UpdateRunState::RollingBack) {
            return;
        }
        if ($current !== UpdateRunState::RollbackRequested) {
            $this->runs->transition($runId, UpdateRunState::RollbackRequested, [
                'rollback_started_at' => now()->toIso8601String(),
            ]);
        }
        $this->runs->transition($runId, UpdateRunState::RollingBack, [
            'rollback_agent_started_at' => now()->toIso8601String(),
        ]);
    }

    public function complete(int $runId): void
    {
        $this->assertState($runId, UpdateRunState::HealthChecking);
        $health = $this->health->snapshot();
        if (($health['database_ok'] ?? false) !== true || ($health['valkey_ok'] ?? false) !== true) {
            throw new RuntimeException('Post-update health gate failed.');
        }

        $this->runs->transition($runId, UpdateRunState::Completed, [
            'completed_at' => now()->toIso8601String(),
        ]);
        $this->safety->leaveRecoveryMode();
    }

    /**
     * Backup restore returns the database to the point before migration/activation,
     * including the update_runs row. Reconcile that deliberately reverted ledger
     * to an explicit rolled_back terminal record after the old release is healthy.
     */
    public function reconcileRolledBackAfterRestore(int $runId, string $backupId): void
    {
        $health = $this->health->snapshot();
        if (($health['database_ok'] ?? false) !== true || ($health['valkey_ok'] ?? false) !== true) {
            throw new RuntimeException('Rollback health gate failed.');
        }

        DB::transaction(function () use ($runId, $backupId): void {
            $run = DB::table('update_runs')->where('id', $runId)->lockForUpdate()->first();
            if ($run === null) {
                throw new DomainException('Restored update run ledger row is missing.');
            }
            $metadata = json_decode((string) ($run->metadata ?? '{}'), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($metadata)) {
                $metadata = [];
            }

            DB::table('update_runs')->where('id', $runId)->update([
                'status' => UpdateRunState::RolledBack->value,
                'metadata' => json_encode([
                    ...$metadata,
                    'backup_id' => $backupId,
                    'rollback_reconciled_at' => now()->toIso8601String(),
                ], JSON_THROW_ON_ERROR),
                'failure_code' => null,
                'failure_message' => null,
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
        });

        if ($this->safety->recoveryMode()) {
            $this->safety->leaveRecoveryMode();
        }
    }

    public function backupId(int $runId): string
    {
        $metadata = $this->runs->metadata($this->runs->find($runId));
        $backupId = $metadata['backup_id'] ?? null;
        if (! is_string($backupId) || $backupId === '') {
            throw new DomainException('Update run has no verified rollback backup reference.');
        }

        return $backupId;
    }

    public function fail(int $runId, string $code, string $message): void
    {
        $this->runs->fail($runId, $code, $message, [
            'agent_failed_at' => now()->toIso8601String(),
        ]);
    }

    private function assertState(int $runId, UpdateRunState $state): stdClass
    {
        $run = $this->runs->find($runId);
        if ((string) $run->status !== $state->value) {
            throw new DomainException("Update run #{$runId} must be in [{$state->value}] state.");
        }

        return $run;
    }
}
