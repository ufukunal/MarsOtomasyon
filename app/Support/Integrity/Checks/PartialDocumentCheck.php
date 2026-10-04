<?php

namespace App\Support\Integrity\Checks;

use App\Actions\Documents\ResolveSourceLineage;
use App\Actions\Documents\SourceLineAvailability;
use App\Enums\DocumentType;
use App\Models\Period\DocumentLine;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class PartialDocumentCheck implements IntegrityCheck
{
    public function __construct(
        private readonly ResolveSourceLineage $lineage,
        private readonly SourceLineAvailability $availability,
    ) {}

    public function name(): string
    {
        return 'partials';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('document_lines')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable'],
            );
        }

        $lines = DocumentLine::query()->with('document')->orderBy('id')->get();
        $mismatches = [];

        foreach ($lines as $line) {
            if ($line->source_line_id !== null) {
                try {
                    $this->lineage->handle($line);
                } catch (Throwable $exception) {
                    $mismatches[] = [
                        'line_id' => $line->id,
                        'reason' => 'source_line_cycle_or_invalid',
                        'detail' => $exception->getMessage(),
                    ];

                    continue;
                }
            }

            if ($line->document->document_type === DocumentType::SalesOrder) {
                $remaining = $this->availability->orderRemaining($line);

                if (bccomp($remaining, '0', 3) < 0) {
                    $mismatches[] = [
                        'line_id' => $line->id,
                        'reason' => 'order_over_fulfilled',
                        'remaining' => $remaining,
                    ];
                }

                if ($line->document->status === 'closed' && bccomp($remaining, '0', 3) !== 0) {
                    $mismatches[] = [
                        'line_id' => $line->id,
                        'reason' => 'closed_order_has_remaining',
                        'remaining' => $remaining,
                    ];
                }
            }

            if ($line->document->document_type === DocumentType::Dispatch
                && $line->document->status === 'posted') {
                $remaining = $this->availability->dispatchRemaining($line);

                if (bccomp($remaining, '0', 3) < 0) {
                    $mismatches[] = [
                        'line_id' => $line->id,
                        'reason' => 'dispatch_over_invoiced',
                        'remaining' => $remaining,
                    ];
                }
            }
        }

        return new IntegrityResult(
            checked: $lines->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
