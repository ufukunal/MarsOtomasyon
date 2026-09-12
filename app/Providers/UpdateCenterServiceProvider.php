<?php

namespace App\Providers;

use App\Modules\UpdateCenter\UpdateAgentCoordinator;
use App\Modules\UpdateCenter\UpdateRunState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Throwable;

final class UpdateCenterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        Artisan::command('mars:update:agent-claim', function (UpdateAgentCoordinator $coordinator): int {
            $claim = $coordinator->claim();
            $this->line(json_encode($claim ?? ['action' => 'none'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return 0;
        })->purpose('Atomically claim the next queued Update Center apply or rollback run');

        Artisan::command('mars:update:agent-artifact {run}', function (UpdateAgentCoordinator $coordinator): int {
            $runId = $this->positiveRunId();
            $this->line(json_encode($coordinator->artifact($runId), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return 0;
        })->purpose('Return verified staged artifact metadata for the host deploy agent');

        Artisan::command('mars:update:agent-backup {run}', function (UpdateAgentCoordinator $coordinator): int {
            $this->line($coordinator->createBackup($this->positiveRunId()));

            return 0;
        })->purpose('Create and verify the mandatory pre-update rollback backup');

        Artisan::command('mars:update:agent-maintenance {run}', function (UpdateAgentCoordinator $coordinator): int {
            $coordinator->enterMaintenance($this->positiveRunId());
            $this->info('maintenance-ready');

            return 0;
        })->purpose('Run apply preflight and enter shared recovery/maintenance mode');

        Artisan::command('mars:update:agent-state {run} {state}', function (UpdateAgentCoordinator $coordinator): int {
            $stateArgument = $this->argument('state');
            if (! is_string($stateArgument)) {
                throw new InvalidArgumentException('Update state is required.');
            }
            $state = UpdateRunState::tryFrom($stateArgument)
                ?? throw new InvalidArgumentException('Unknown update state.');
            if (! in_array($state, [UpdateRunState::Migrating, UpdateRunState::Activating, UpdateRunState::HealthChecking], true)) {
                throw new InvalidArgumentException('Host agent may only mark migrating, activating or health_checking through this command.');
            }

            $coordinator->transition($this->positiveRunId(), $state);
            $this->info($state->value);

            return 0;
        })->purpose('Advance the host-controlled migration/activation lifecycle');

        Artisan::command('mars:update:agent-complete {run}', function (UpdateAgentCoordinator $coordinator): int {
            $coordinator->complete($this->positiveRunId());
            $this->info('completed');

            return 0;
        })->purpose('Close a healthy update run and leave recovery mode');

        Artisan::command('mars:update:agent-backup-id {run}', function (UpdateAgentCoordinator $coordinator): int {
            $this->line($coordinator->backupId($this->positiveRunId()));

            return 0;
        })->purpose('Return the verified rollback backup for an update run');

        Artisan::command('mars:update:agent-rollback-complete {run}', function (UpdateAgentCoordinator $coordinator): int {
            $coordinator->rolledBack($this->positiveRunId());
            $this->info('rolled-back');

            return 0;
        })->purpose('Close a healthy rollback and leave recovery mode');

        Artisan::command('mars:update:agent-fail {run} {code} {message?}', function (UpdateAgentCoordinator $coordinator): int {
            $code = $this->argument('code');
            $message = $this->argument('message');
            if (! is_string($code) || trim($code) === '') {
                throw new InvalidArgumentException('Failure code is required.');
            }

            try {
                $coordinator->fail(
                    $this->positiveRunId(),
                    trim($code),
                    is_string($message) && $message !== '' ? $message : 'Host deploy agent failed.',
                );
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return 1;
            }

            return 0;
        })->purpose('Fail an update run with a normalized host-agent error');
    }
}
