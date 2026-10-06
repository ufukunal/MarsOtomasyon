<?php

namespace App\Support\Integrity\Checks;

use App\Models\ReportFilterPreset;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use App\Support\Reporting\ReportRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ReportPresetIntegrityCheck implements IntegrityCheck
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function name(): string
    {
        return 'report_presets';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('master')->hasTable('report_filter_presets')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable'],
            );
        }

        $definitions = [];

        foreach ($this->registry->definitions() as $definition) {
            $definitions[$definition->key] = $definition;
        }

        $mismatches = [];
        $presets = ReportFilterPreset::query()->orderBy('id')->get();

        foreach ($presets as $preset) {
            $reason = $this->scopeReason($preset);

            if ($reason !== null) {
                $mismatches[] = ['preset_id' => $preset->id, 'reason' => $reason];

                continue;
            }

            $definition = $definitions[$preset->report_key] ?? null;

            if ($definition === null) {
                $mismatches[] = ['preset_id' => $preset->id, 'reason' => 'report_key_unresolvable'];

                continue;
            }

            try {
                $filterMap = $definition->filterMap();
                $columnMap = $definition->columnMap();

                foreach (array_keys($preset->filters ?? []) as $key) {
                    if (! isset($filterMap[$key])) {
                        throw new \DomainException('filter_key_unresolvable');
                    }
                    $filterMap[$key]->normalize($preset->filters[$key]);
                }

                foreach (($preset->columns ?? []) as $key) {
                    if (! is_string($key) || ! isset($columnMap[$key])) {
                        throw new \DomainException('column_key_unresolvable');
                    }
                }

                foreach (($preset->sort ?? []) as $item) {
                    if (! is_array($item)
                        || ! isset($item['key'])
                        || ! isset($columnMap[(string) $item['key']])
                        || ! $columnMap[(string) $item['key']]->sortable
                        || ! in_array(strtolower((string) ($item['direction'] ?? 'asc')), ['asc', 'desc'], true)) {
                        throw new \DomainException('sort_key_unresolvable');
                    }
                }
            } catch (Throwable $exception) {
                $mismatches[] = [
                    'preset_id' => $preset->id,
                    'reason' => in_array($exception->getMessage(), [
                        'filter_key_unresolvable',
                        'column_key_unresolvable',
                        'sort_key_unresolvable',
                    ], true)
                        ? $exception->getMessage()
                        : 'preset_definition_invalid',
                ];
            }
        }

        return new IntegrityResult(
            checked: $presets->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function scopeReason(ReportFilterPreset $preset): ?string
    {
        if ($preset->is_shared && $preset->user_id !== null) {
            return 'shared_preset_user_must_be_null';
        }

        if (! $preset->is_shared && $preset->user_id === null) {
            return 'personal_preset_user_missing';
        }

        if ($preset->user_id !== null) {
            $belongs = DB::connection('master')
                ->table('company_user')
                ->where('company_id', $preset->company_id)
                ->where('user_id', $preset->user_id)
                ->exists();

            if (! $belongs) {
                return 'personal_preset_user_company_scope_invalid';
            }
        }

        return null;
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
