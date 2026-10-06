<?php

namespace App\Support\Integrity\Checks;

use App\Actions\Documents\CalculateDocumentTotals;
use App\Models\Period\Document;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class DocumentTotalCheck implements IntegrityCheck
{
    public function __construct(private readonly CalculateDocumentTotals $calculator) {}

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

        $documents = Document::query()->with('lines')->orderBy('id')->get();
        $mismatches = [];

        foreach ($documents as $document) {
            $headerCalculated = bcadd(
                bcadd((string) $document->tax_base, (string) $document->vat_amount, 4),
                (string) $document->rounding_difference,
                4,
            );

            if (bccomp((string) $document->grand_total, $headerCalculated, 4) !== 0) {
                $mismatches[] = [
                    'document_id' => $document->id,
                    'reason' => 'header_total_invariant',
                    'stored' => (string) $document->grand_total,
                    'calculated' => $headerCalculated,
                ];

                continue;
            }

            if ($document->document_type->isHeaderAmount()) {
                if ($document->lines->isNotEmpty()
                    || bccomp((string) $document->subtotal, (string) $document->tax_base, 4) !== 0
                    || bccomp((string) $document->tax_base, (string) $document->grand_total, 4) !== 0
                    || bccomp((string) $document->discount_amount, '0', 4) !== 0
                    || bccomp((string) $document->vat_amount, '0', 4) !== 0
                    || bccomp((string) $document->rounding_difference, '0', 4) !== 0) {
                    $mismatches[] = [
                        'document_id' => $document->id,
                        'reason' => 'header_amount_shape',
                    ];
                }

                continue;
            }

            if (! $document->document_type->isLineCalculated()) {
                continue;
            }

            try {
                $totals = $this->calculator->handle(
                    $document->lines->map(fn ($line) => [
                        'quantity' => (string) $line->quantity,
                        'unit_price' => (string) $line->unit_price,
                        'line_discount_rate' => (string) $line->line_discount_rate,
                        'line_discount_amount' => (string) $line->line_discount_amount,
                        'vat_rate' => (string) $line->vat_rate,
                    ])->all(),
                    (string) $document->discount_rate,
                    (string) $document->discount_amount,
                );
            } catch (Throwable $exception) {
                $mismatches[] = [
                    'document_id' => $document->id,
                    'reason' => 'calculation_error',
                    'detail' => $exception->getMessage(),
                ];

                continue;
            }

            foreach ($document->lines->values() as $index => $line) {
                if (bccomp((string) $line->line_total, $totals->lines[$index]->lineTotal, 4) !== 0) {
                    $mismatches[] = [
                        'document_id' => $document->id,
                        'line_id' => $line->id,
                        'reason' => 'line_total_mismatch',
                    ];
                }
            }

            foreach ([
                'discount_amount' => $totals->discountAmount,
                'subtotal' => $totals->subtotal,
                'tax_base' => $totals->taxBase,
                'vat_amount' => $totals->vatAmount,
                'rounding_difference' => $totals->roundingDifference,
                'grand_total' => $totals->grandTotal,
            ] as $field => $expected) {
                if (bccomp((string) $document->getAttribute($field), $expected, 4) !== 0) {
                    $mismatches[] = [
                        'document_id' => $document->id,
                        'reason' => $field.'_mismatch',
                        'stored' => (string) $document->getAttribute($field),
                        'calculated' => $expected,
                    ];
                }
            }
        }

        return new IntegrityResult(
            checked: $documents->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
