<?php

namespace App\Modules\Reports\Bi;

use App\Foundation\Operations\ProductionSafetyState;
use Illuminate\Support\Facades\DB;
use stdClass;
use Throwable;

final readonly class BiScheduleRunner
{
    public function __construct(
        private BiRuntimeAuthorizer $authorizer,
        private BiExportService $exports,
        private ProductionSafetyState $safety,
    ) {}

    /** @return array{succeeded:int,failed:int,skipped:int} */
    public function runDue(): array
    {
        if (! $this->safety->schedulerWorkEnabled()) {
            return ['succeeded' => 0, 'failed' => 0, 'skipped' => 1];
        }

        $counts = ['succeeded' => 0, 'failed' => 0, 'skipped' => 0];
        /** @var \Illuminate\Support\Collection<int, stdClass> $schedules */
        $schedules = DB::table('bi_export_schedules')
            ->where('is_enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('id')
            ->get();

        foreach ($schedules as $schedule) {
            $companyId = (int) $schedule->company_id;
            $userId = (int) $schedule->created_by_user_id;
            $includePii = (bool) $schedule->include_pii;
            $scheduleId = (int) $schedule->id;
            $intervalMinutes = max(5, (int) $schedule->interval_minutes);

            if ($userId < 1 || ! $this->authorizer->allows($companyId, $userId, $includePii)) {
                $this->recordAuthorizationFailure($schedule);
                $this->advance($scheduleId, $intervalMinutes);
                $counts['failed']++;

                continue;
            }

            try {
                $fields = json_decode((string) $schedule->fields, true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($fields) || $fields === []) {
                    throw new \DomainException('Scheduled BI export fields are invalid.');
                }

                /** @var list<string> $fields */
                $this->exports->export(
                    companyId: $companyId,
                    datasetKey: (string) $schedule->dataset_key,
                    format: (string) $schedule->format,
                    requestedFields: array_values($fields),
                    watermark: $schedule->watermark === null ? null : (string) $schedule->watermark,
                    includePii: $includePii,
                    branchId: $schedule->branch_id === null ? null : (int) $schedule->branch_id,
                    scheduleId: $scheduleId,
                );
                $counts['succeeded']++;
            } catch (Throwable $exception) {
                DB::table('bi_export_schedules')->where('id', $scheduleId)->update([
                    'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);
                $counts['failed']++;
            } finally {
                $this->advance($scheduleId, $intervalMinutes);
            }
        }

        return $counts;
    }

    private function recordAuthorizationFailure(stdClass $schedule): void
    {
        $message = 'Scheduled BI export authorization is no longer valid.';
        DB::table('bi_export_runs')->insert([
            'company_id' => (int) $schedule->company_id,
            'branch_id' => $schedule->branch_id,
            'schedule_id' => (int) $schedule->id,
            'dataset_key' => (string) $schedule->dataset_key,
            'schema_version' => (int) $schedule->schema_version,
            'format' => (string) $schedule->format,
            'fields' => (string) $schedule->fields,
            'input_watermark' => $schedule->watermark,
            'status' => 'failed',
            'row_count' => 0,
            'last_error' => $message,
            'started_at' => now(),
            'finished_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('bi_export_schedules')->where('id', (int) $schedule->id)->update([
            'last_error' => $message,
            'last_run_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function advance(int $scheduleId, int $intervalMinutes): void
    {
        DB::table('bi_export_schedules')->where('id', $scheduleId)->update([
            'next_run_at' => now()->addMinutes($intervalMinutes),
            'last_run_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
