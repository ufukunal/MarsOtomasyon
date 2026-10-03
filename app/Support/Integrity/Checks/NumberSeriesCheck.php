<?php

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NumberSeriesCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'numbers';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        $series = DB::connection('period')->table('number_series')
            ->orderBy('document_type')
            ->orderBy('year')
            ->get();

        if (! Schema::connection('period')->hasTable('documents')) {
            return new IntegrityResult(
                checked: $series->count(),
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: [
                    'status' => 'infrastructure_only',
                    'reason' => 'documents Faz 3 içinde kurulunca gerçek belge/series gap ve tekrar kontrolü aktif olur',
                ],
            );
        }

        $mismatches = [];

        foreach ($series as $item) {
            $prefix = preg_quote((string) $item->prefix, '/');
            $year = (int) $item->year;

            $numbers = DB::connection('period')->table('documents')
                ->where('document_type', $item->document_type)
                ->whereNotNull('number')
                ->whereYear('document_date', $year)
                ->pluck('number')
                ->all();

            $seen = [];
            $max = 0;

            foreach ($numbers as $number) {
                if (! preg_match("/^{$prefix}-{$year}-(\d+)$/", (string) $number, $match)) {
                    $mismatches[] = [
                        'document_type' => $item->document_type,
                        'number' => $number,
                        'reason' => 'format_mismatch',
                    ];

                    continue;
                }

                $numeric = (int) $match[1];

                if (isset($seen[$numeric])) {
                    $mismatches[] = [
                        'document_type' => $item->document_type,
                        'number' => $number,
                        'reason' => 'duplicate_numeric_part',
                    ];
                }

                $seen[$numeric] = true;
                $max = max($max, $numeric);
            }

            if ($max > (int) $item->last_number) {
                $mismatches[] = [
                    'document_type' => $item->document_type,
                    'year' => $year,
                    'series_last_number' => (int) $item->last_number,
                    'document_max_number' => $max,
                    'reason' => 'series_behind_documents',
                ];
            }
        }

        return new IntegrityResult(
            checked: $series->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
