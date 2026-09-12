<?php

namespace App\Console\Commands;

use App\Modules\UpdateCenter\UpdateArtifactStager;
use App\Modules\UpdateCenter\UpdateRunState;
use App\Modules\UpdateCenter\UpdateRunStore;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class MarsUpdateExecutorCommand extends Command
{
    protected $signature = 'mars:update-executor
        {action : show|stage|applying|health|activate|rollback-requested|rolled-back|backup|fail}
        {run : Update run id}
        {--field= : For show, one of status,target_version,channel,stage_path,apply_requested_at}
        {--backup-id= : Safety backup identifier for backup action}
        {--failure-code=executor_failed : Normalized failure code}
        {--failure-message=Trusted update executor failed. : Normalized failure message}';

    protected $description = 'Trusted host-side Update Center executor control plane; never exposed as an HTTP shell';

    public function handle(UpdateRunStore $runs, UpdateArtifactStager $stager): int
    {
        $runId = (int) $this->argument('run');
        if ($runId < 1) {
            throw new InvalidArgumentException('Update run id must be a positive integer.');
        }

        $action = strtolower(trim((string) $this->argument('action')));
        match ($action) {
            'show' => $this->showRun($runs, $runId),
            'stage' => $stager->stage($runId),
            'applying' => $runs->transition($runId, UpdateRunState::Applying, ['executor_started_at' => now()->toIso8601String()]),
            'health' => $runs->transition($runId, UpdateRunState::HealthCheck, ['health_check_started_at' => now()->toIso8601String()]),
            'activate' => $runs->transition($runId, UpdateRunState::Activated, ['activated_at' => now()->toIso8601String()]),
            'rollback-requested' => $runs->transition($runId, UpdateRunState::RollbackRequested, ['rollback_requested_at' => now()->toIso8601String(), 'rollback_source' => 'executor']),
            'rolled-back' => $runs->transition($runId, UpdateRunState::RolledBack, ['rolled_back_at' => now()->toIso8601String()]),
            'backup' => $this->recordBackup($runs, $runId),
            'fail' => $runs->fail(
                $runId,
                trim((string) $this->option('failure-code')),
                trim((string) $this->option('failure-message')),
                ['failed_at' => now()->toIso8601String(), 'failure_source' => 'executor'],
            ),
            default => throw new InvalidArgumentException('Unknown executor action.'),
        };

        if ($action !== 'show') {
            $this->info('update-run:'.$runId.':'.$action.':ok');
        }

        return self::SUCCESS;
    }

    private function showRun(UpdateRunStore $runs, int $runId): void
    {
        $run = $runs->find($runId);
        $field = trim((string) $this->option('field'));
        $metadata = json_decode((string) ($run->metadata ?? '{}'), true, flags: JSON_THROW_ON_ERROR);
        $metadata = is_array($metadata) ? $metadata : [];

        $value = match ($field) {
            'status' => (string) $run->status,
            'target_version' => (string) $run->target_version,
            'channel' => (string) $run->channel,
            'stage_path' => (string) ($metadata['stage_path'] ?? ''),
            'apply_requested_at' => (string) ($metadata['apply_requested_at'] ?? ''),
            default => throw new InvalidArgumentException('A supported --field is required for show.'),
        };

        $this->line($value);
    }

    private function recordBackup(UpdateRunStore $runs, int $runId): void
    {
        $backupId = trim((string) $this->option('backup-id'));
        if (preg_match('/^[0-9a-f-]{36}$/i', $backupId) !== 1) {
            throw new InvalidArgumentException('A UUID safety backup id is required.');
        }

        $runs->annotate($runId, [
            'safety_backup_id' => $backupId,
            'safety_backup_created_at' => now()->toIso8601String(),
        ]);
    }
}
