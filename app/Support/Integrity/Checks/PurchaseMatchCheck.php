<?php

namespace App\Support\Integrity\Checks;

use App\Actions\Purchases\PurchaseLineAvailability;
use App\Actions\Purchases\ResolvePurchaseLineage;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\PurchaseMatch;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class PurchaseMatchCheck implements IntegrityCheck
{
    public function __construct(
        private readonly ResolvePurchaseLineage $lineage,
        private readonly PurchaseLineAvailability $availability,
    ) {}

    public function name(): string
    {
        return 'purchases';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('purchase_matches')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable'],
            );
        }

        $mismatches = [];
        $checked = 0;

        $orderLines = DocumentLine::query()
            ->whereHas('document', fn ($query) => $query
                ->where('document_type', DocumentType::PurchaseOrder->value)
                ->whereIn('status', ['approved', 'sent']))
            ->get();

        foreach ($orderLines as $line) {
            $checked++;
            $remaining = $this->availability->orderReceiptRemaining($line);

            if (bccomp($remaining, '0', 3) < 0) {
                $mismatches[] = [
                    'line_id' => $line->id,
                    'reason' => 'purchase_order_over_received',
                    'remaining' => $remaining,
                ];
            }
        }

        $receipts = Document::query()
            ->where('document_type', DocumentType::GoodsReceipt->value)
            ->where('status', 'posted')
            ->whereDoesntHave('incomingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
            ->whereDoesntHave('outgoingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
            ->with('lines')
            ->get();

        foreach ($receipts as $receipt) {
            foreach ($receipt->lines as $line) {
                $checked++;
                $remaining = $this->availability->receiptInvoiceRemaining($line);

                if (bccomp($remaining, '0', 3) < 0) {
                    $mismatches[] = [
                        'line_id' => $line->id,
                        'reason' => 'goods_receipt_over_invoiced',
                        'remaining' => $remaining,
                    ];
                }
            }
        }

        $invoices = Document::query()
            ->where('document_type', DocumentType::SupplierInvoice->value)
            ->where('status', 'posted')
            ->whereDoesntHave('incomingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
            ->whereDoesntHave('outgoingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
            ->with('lines')
            ->get();

        foreach ($invoices as $invoice) {
            foreach ($invoice->lines as $line) {
                $checked++;

                if ($line->line_kind === 'service') {
                    if (PurchaseMatch::query()
                        ->where('supplier_invoice_line_id', $line->id)
                        ->exists()) {
                        $mismatches[] = [
                            'line_id' => $line->id,
                            'reason' => 'service_line_must_not_have_three_way_match',
                        ];
                    }

                    continue;
                }

                try {
                    $lineage = $this->lineage->handle($line);
                } catch (Throwable $exception) {
                    $mismatches[] = [
                        'line_id' => $line->id,
                        'reason' => 'purchase_lineage_invalid',
                        'detail' => $exception->getMessage(),
                    ];

                    continue;
                }

                $match = PurchaseMatch::query()
                    ->where('supplier_invoice_line_id', $line->id)
                    ->first();

                if (! $match
                    || $lineage['purchase_order_line_id'] !== (int) $match->purchase_order_line_id
                    || $lineage['goods_receipt_line_id'] !== (int) $match->goods_receipt_line_id
                    || bccomp((string) $match->matched_quantity, (string) $line->quantity, 3) !== 0) {
                    $mismatches[] = [
                        'line_id' => $line->id,
                        'reason' => 'three_way_match_mismatch',
                    ];

                    continue;
                }

                if ($line->line_kind === 'stock'
                    && ($match->provisional_unit_cost_try === null
                        || $match->cost_unit_try === null
                        || $match->previous_moving_average === null
                        || $match->new_moving_average === null
                        || $match->cost_value_delta === null)) {
                    $mismatches[] = [
                        'line_id' => $line->id,
                        'reason' => 'purchase_cost_snapshot_missing',
                    ];
                }
            }
        }

        return new IntegrityResult(
            checked: $checked,
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
