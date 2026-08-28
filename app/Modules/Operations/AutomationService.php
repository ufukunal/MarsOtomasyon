<?php

namespace App\Modules\Operations;

use App\Modules\Operations\Jobs\ExecuteAutomationRun;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AutomationService
{
    /** @param array<string,mixed> $input */
    public function fire(int $companyId, string $eventType, string $triggerKey, array $input): int
    {
        $rules = DB::table('automation_rules')
            ->where('company_id', $companyId)
            ->where('event_type', $eventType)
            ->where('is_enabled', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
        $created = 0;
        foreach ($rules as $rule) {
            $conditions = $rule->conditions === null ? [] : json_decode((string) $rule->conditions, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($conditions) || ! $this->matches($conditions, $input)) {
                continue;
            }
            $runId = DB::transaction(function () use ($companyId, $rule, $triggerKey, $input): ?int {
                $existing = DB::table('automation_runs')
                    ->where('company_id', $companyId)
                    ->where('rule_id', $rule->id)
                    ->where('trigger_key', $triggerKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    $existingInput = json_decode((string) $existing->input, true, flags: JSON_THROW_ON_ERROR);
                    if ($existingInput !== $input) {
                        throw new DomainException('Automation trigger replay payload drift detected.');
                    }

                    return null;
                }
                $status = (bool) $rule->requires_approval ? 'pending_approval' : 'queued';
                $id = (int) DB::table('automation_runs')->insertGetId([
                    'company_id' => $companyId,
                    'rule_id' => $rule->id,
                    'trigger_key' => $triggerKey,
                    'status' => $status,
                    'input' => json_encode($input, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($status === 'queued') {
                    ExecuteAutomationRun::dispatch($id)->afterCommit();
                }

                return $id;
            });
            if ($runId !== null) {
                $created++;
            }
        }

        return $created;
    }

    public function approve(int $companyId, int $runId, int $userId): void
    {
        DB::transaction(function () use ($companyId, $runId, $userId): void {
            $run = DB::table('automation_runs')->where('company_id', $companyId)->where('id', $runId)->lockForUpdate()->first();
            if ($run === null || (string) $run->status !== 'pending_approval') {
                throw new DomainException('Automation run is not awaiting approval.');
            }
            DB::table('automation_runs')->where('id', $runId)->update([
                'status' => 'approved', 'approved_by_user_id' => $userId, 'approved_at' => now(), 'updated_at' => now(),
            ]);
            ExecuteAutomationRun::dispatch($runId)->afterCommit();
        });
    }

    public function reject(int $companyId, int $runId, int $userId): void
    {
        DB::transaction(function () use ($companyId, $runId, $userId): void {
            $run = DB::table('automation_runs')->where('company_id', $companyId)->where('id', $runId)->lockForUpdate()->first();
            if ($run === null || (string) $run->status !== 'pending_approval') {
                throw new DomainException('Automation run is not awaiting approval.');
            }
            DB::table('automation_runs')->where('id', $runId)->update([
                'status' => 'rejected', 'approved_by_user_id' => $userId, 'approved_at' => now(), 'finished_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function execute(int $runId, NotificationService $notifications, ChannelService $channels, SecurityCenter $security): void
    {
        $run = DB::table('automation_runs as r')
            ->join('automation_rules as a', 'a.id', '=', 'r.rule_id')
            ->where('r.id', $runId)
            ->select('r.*', 'a.action_type', 'a.action_payload')
            ->first();
        if ($run === null || in_array((string) $run->status, ['succeeded', 'rejected'], true)) {
            return;
        }
        if (! in_array((string) $run->status, ['queued', 'approved', 'failed'], true)) {
            throw new DomainException('Automation run cannot execute from current status.');
        }
        DB::table('automation_runs')->where('id', $runId)->update(['status' => 'running', 'started_at' => now(), 'last_error' => null, 'updated_at' => now()]);

        try {
            $input = json_decode((string) $run->input, true, flags: JSON_THROW_ON_ERROR);
            $action = json_decode((string) $run->action_payload, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($input) || ! is_array($action)) {
                throw new DomainException('Automation payload is invalid.');
            }
            $output = match ((string) $run->action_type) {
                'notify' => ['delivery_id' => $notifications->enqueueTemplate(
                    (int) $run->company_id,
                    (string) ($action['template_key'] ?? ''),
                    (string) ($action['channel'] ?? 'email'),
                    $this->resolveString($action['recipient'] ?? '', $input),
                    $this->scalarVariables($input),
                    (string) Str::uuid(),
                )],
                'integration_sync' => ['sync_effect_id' => $channels->scheduleSync(
                    (int) $run->company_id,
                    (int) ($action['connection_id'] ?? 0),
                    (string) ($action['operation'] ?? ''),
                    (string) ($action['entity_type'] ?? 'entity'),
                    $this->resolveString($action['entity_id'] ?? '', $input),
                    is_array($action['payload'] ?? null) ? $action['payload'] : $input,
                    (string) Str::uuid(),
                )],
                'security_event' => ['security_event_id' => $security->record(
                    (int) $run->company_id,
                    null,
                    (string) ($action['event_type'] ?? 'automation.event'),
                    (string) ($action['severity'] ?? 'info'),
                    null,
                    null,
                    $input,
                )],
                default => throw new DomainException('Unknown automation action type.'),
            };
            DB::table('automation_runs')->where('id', $runId)->update([
                'status' => 'succeeded', 'output' => json_encode($output, JSON_THROW_ON_ERROR), 'finished_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            DB::table('automation_runs')->where('id', $runId)->update([
                'status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 4000), 'finished_at' => now(), 'updated_at' => now(),
            ]);
            throw $exception;
        }
    }

    /** @param array<string,mixed> $conditions @param array<string,mixed> $input */
    private function matches(array $conditions, array $input): bool
    {
        foreach ($conditions as $path => $expected) {
            if (data_get($input, (string) $path) !== $expected) {
                return false;
            }
        }

        return true;
    }

    /** @param mixed $value @param array<string,mixed> $input */
    private function resolveString(mixed $value, array $input): string
    {
        $value = (string) $value;
        if (str_starts_with($value, '$.')) {
            $resolved = data_get($input, substr($value, 2));

            return is_scalar($resolved) ? (string) $resolved : '';
        }

        return $value;
    }

    /** @param array<string,mixed> $input @return array<string,scalar|null> */
    private function scalarVariables(array $input): array
    {
        $result = [];
        foreach ($input as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $result[(string) $key] = $value;
            }
        }

        return $result;
    }
}
