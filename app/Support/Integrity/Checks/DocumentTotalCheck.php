<?php

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentTotalCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'documents';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('documents')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable', 'reason' => 'documents schema henüz kurulmadı'],
            );
        }

        $rows = DB::connection('period')->table('documents')
            ->select([
                'id',
                'document_type',
                'number',
                'tax_base',
                'vat_amount',
                'rounding_difference',
                'grand_total',
            ])
            ->get();

        $mismatches = [];

        foreach ($rows as $row) {
            $calculated = bcadd(
                bcadd((string) $row->tax_base, (string) $row->vat_amount, 4),
                (string) $row->rounding_difference,
                4,
            );

            if (bccomp((string) $row->grand_total, $calculated, 4) !== 0) {
                $mismatches[] = [
                    'document_id' => $row->id,
                    'document_type' => $row->document_type,
                    'number' => $row->number,
                    'stored' => (string) $row->grand_total,
                    'calculated' => $calculated,
                ];
            }
        }

        return new IntegrityResult(
            checked: $rows->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
            meta: [
                'scope' => 'Faz 0 header total invariant; line-calculated genişletmesi documents/document_lines sahibi görevlerde tamamlanır',
            ],
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
