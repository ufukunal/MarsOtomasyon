<?php

namespace App\Support\Integrity\Checks;

use App\Enums\DocumentType;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use App\Models\Period\DocumentRelation;
use App\Models\Period\QuarantineEntry;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ReturnIntegrityCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'returns';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('documents')) {
            return new IntegrityResult(0, [], $this->elapsed($started), ['status' => 'not_applicable']);
        }

        $mismatches = [];
        $documents = Document::query()
            ->whereIn('document_type', [
                DocumentType::SalesReturn->value,
                DocumentType::PurchaseReturn->value,
            ])
            ->where('status', 'posted')
            ->with('lines')
            ->get();

        foreach ($documents as $document) {
            $isReversal = DocumentRelation::query()
                ->where('relation_type', 'reversal_of')
                ->where('source_document_id', $document->id)
                ->exists();

            $transaction = ContactTransaction::query()->where('document_id', $document->id)->first();

            if (! $transaction) {
                $mismatches[] = ['document_id' => $document->id, 'reason' => 'return_contact_transaction_missing'];
                continue;
            }

            if ($document->document_type === DocumentType::SalesReturn && ! $isReversal) {
                $entries = QuarantineEntry::query()
                    ->where('source_document_type', DocumentType::SalesReturn->value)
                    ->where('source_document_id', $document->id)
                    ->get();

                if ($entries->count() !== $document->lines->where('line_kind', 'stock')->count()) {
                    $mismatches[] = ['document_id' => $document->id, 'reason' => 'return_quarantine_count'];
                }
            }

            $direction = $document->document_type === DocumentType::SalesReturn ? 'credit' : 'debit';

            if ($isReversal) {
                $direction = $direction === 'credit' ? 'debit' : 'credit';
            }

            if ($transaction->direction !== $direction) {
                $mismatches[] = ['document_id' => $document->id, 'reason' => 'return_contact_direction'];
            }

            foreach ($document->lines as $line) {
                if ($isReversal || $line->source_line_id === null) {
                    continue;
                }

                $source = $line->sourceLine()->with('document')->first();

                if (! $source
                    || ($document->document_type === DocumentType::SalesReturn
                        && $source->document->document_type !== DocumentType::SalesInvoice)
                    || ($document->document_type === DocumentType::PurchaseReturn
                        && $source->document->document_type !== DocumentType::SupplierInvoice)) {
                    $mismatches[] = ['line_id' => $line->id, 'reason' => 'return_source_invalid'];
                }
            }

            if ($document->document_type === DocumentType::PurchaseReturn && ! $isReversal) {
                $expected = $document->lines
                    ->where('line_kind', 'stock')
                    ->reduce(fn (string $sum, $line) => bcadd($sum, (string) $line->base_quantity, 3), '0.000');
                $actual = (string) DB::connection('period')->table('stock_movements')
                    ->where('document_type', DocumentType::PurchaseReturn->value)
                    ->where('document_id', $document->id)
                    ->where('direction', 'out')
                    ->selectRaw('COALESCE(SUM(quantity), 0)::text AS qty')
                    ->value('qty');

                if (bccomp($expected, $actual, 3) !== 0) {
                    $mismatches[] = ['document_id' => $document->id, 'reason' => 'purchase_return_stock_mismatch'];
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
