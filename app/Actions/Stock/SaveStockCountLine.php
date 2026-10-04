<?php

namespace App\Actions\Stock;

use App\Models\Period\StockCount;
use App\Models\Period\StockCountLine;
use App\Support\Auth\MutationAuthorizer;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaveStockCountLine
{
    public function handle(int $countId, int $lineId, string $countedQuantity, ?string $note = null, ?bool $approved = null): StockCountLine
    {
        MutationAuthorizer::authorize('stock_counts.update');

        if (bccomp($countedQuantity, '0', 3) < 0) {
            throw new DomainException('Sayılan miktar negatif olamaz.');
        }

        return DB::connection('period')->transaction(function () use ($countId, $lineId, $countedQuantity, $note, $approved): StockCountLine {
            $count = StockCount::query()->lockForUpdate()->findOrFail($countId);
            if (! in_array($count->status, ['counting','review'], true)) {
                throw new DomainException('Bu sayımda miktar girişi yapılamaz.');
            }

            $line = StockCountLine::query()->where('stock_count_id', $count->id)->lockForUpdate()->findOrFail($lineId);
            $normalized = bcadd($countedQuantity, '0', 3);
            $line->setAttribute('counted_quantity', $normalized);
            $line->setAttribute('difference', bcsub($normalized, (string) $line->system_quantity, 3));
            $line->note = $note;
            if ($approved !== null) {
                $line->is_approved = $approved;
            }
            $line->save();

            return $line->refresh();
        }, attempts: 3);
    }
}
