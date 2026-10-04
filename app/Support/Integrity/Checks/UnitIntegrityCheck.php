<?php

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnitIntegrityCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'units';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('unit_conversions')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable', 'reason' => 'unit conversion schema henüz kurulmadı'],
            );
        }

        $mismatches = [];
        $conversions = DB::connection('period')
            ->table('unit_conversions')
            ->orderBy('id')
            ->get();

        foreach ($conversions as $conversion) {
            if ((int) $conversion->from_unit_id === (int) $conversion->to_unit_id) {
                $mismatches[] = [
                    'conversion_id' => $conversion->id,
                    'reason' => 'same_unit',
                ];
            }

            if (bccomp((string) $conversion->factor, '0', 6) <= 0) {
                $mismatches[] = [
                    'conversion_id' => $conversion->id,
                    'reason' => 'non_positive_factor',
                ];
            }
        }

        $documentLineCount = 0;

        if (Schema::connection('period')->hasTable('document_lines')
            && Schema::connection('period')->hasColumns('document_lines', [
                'quantity',
                'conversion_factor',
                'base_quantity',
            ])) {
            $documentLines = DB::connection('period')
                ->table('document_lines')
                ->select(['id', 'quantity', 'conversion_factor', 'base_quantity'])
                ->orderBy('id')
                ->get();

            $documentLineCount = $documentLines->count();

            foreach ($documentLines as $line) {
                $calculated = bcmul(
                    (string) $line->quantity,
                    (string) $line->conversion_factor,
                    3,
                );

                if (bccomp($calculated, (string) $line->base_quantity, 3) !== 0) {
                    $mismatches[] = [
                        'document_line_id' => $line->id,
                        'reason' => 'base_quantity_mismatch',
                        'stored' => $line->base_quantity,
                        'calculated' => $calculated,
                    ];
                }
            }
        }

        return new IntegrityResult(
            checked: $conversions->count() + $documentLineCount,
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
            meta: [
                'document_lines_checked' => $documentLineCount,
                'document_snapshot_status' => $documentLineCount > 0 ? 'checked' : 'not_yet_available',
            ],
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
