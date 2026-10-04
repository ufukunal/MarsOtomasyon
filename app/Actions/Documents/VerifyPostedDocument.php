<?php

namespace App\Actions\Documents;

use App\DataObjects\Documents\DocumentPostingContext;
use App\DataObjects\Documents\PostingProfile;
use App\Enums\DocumentType;
use App\Models\Period\BankMovement;
use App\Models\Period\CashMovement;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use DomainException;
use Illuminate\Support\Facades\DB;

final class VerifyPostedDocument
{
    public function __construct(private readonly ResolveSourceLineage $lineage) {}

    public function handle(
        Document $document,
        PostingProfile $profile,
        ?DocumentPostingContext $context = null,
    ): void {
        if ($profile->contactDirection !== null) {
            $transaction = ContactTransaction::query()->where('document_id', $document->id)->first();

            if (! $transaction
                || $transaction->direction !== $profile->contactDirection
                || bccomp((string) $transaction->amount, (string) $document->grand_total, 4) !== 0) {
                throw new DomainException('Belge sonrası cari hareket doğrulaması başarısız.');
            }
        }

        if ($profile->financialIn) {
            $movement = $context?->accountType === 'cash'
                ? CashMovement::query()->where('document_id', $document->id)->first()
                : BankMovement::query()->where('document_id', $document->id)->first();

            if (! $movement
                || $movement->direction !== 'in'
                || bccomp((string) $movement->amount, (string) $document->grand_total, 4) !== 0) {
                throw new DomainException('Tahsilat finans hareketi doğrulaması başarısız.');
            }
        }

        if (! $profile->stockOut) {
            return;
        }

        $expected = [];

        foreach ($document->lines as $line) {
            if ($line->line_kind !== 'stock') {
                continue;
            }

            $lineage = $this->lineage->handle($line);

            if ($document->document_type === DocumentType::SalesInvoice && $lineage['has_dispatch']) {
                continue;
            }

            $key = $line->product_id.':'.$line->location_id;
            $expected[$key] = bcadd($expected[$key] ?? '0.000', (string) $line->base_quantity, 3);
        }

        $actual = DB::connection('period')->table('stock_movements')
            ->where('document_type', $document->document_type->value)
            ->where('document_id', $document->id)
            ->where('direction', 'out')
            ->selectRaw("product_id, location_id, SUM(quantity)::text AS quantity")
            ->groupBy('product_id', 'location_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->product_id.':'.$row->location_id => bcadd((string) $row->quantity, '0', 3),
            ])
            ->all();

        ksort($actual);
        ksort($expected);

        if ($actual !== $expected) {
            throw new DomainException('Belge sonrası stok hareketi doğrulaması başarısız.');
        }
    }
}
