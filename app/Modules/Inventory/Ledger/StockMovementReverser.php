<?php

namespace App\Modules\Inventory\Ledger;

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\StockMovement;
use DomainException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class StockMovementReverser
{
    public function __construct(private StockMovementPoster $poster) {}

    public function reverse(
        int $originalMovementId,
        SourceEffectIdentity $sourceEffect,
        ?string $note = null,
    ): StockMovementPostingResult {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Stok hareketi reversal aynı business transaction içinde çalışmalıdır.');
        }

        $original = StockMovement::query()
            ->where('company_id', $sourceEffect->companyId)
            ->whereKey($originalMovementId)
            ->sharedLock()
            ->first();

        if (! $original instanceof StockMovement) {
            throw new DomainException('Ters stok hareketi hedefi bulunamadı.');
        }
        if ($original->reversal_of_movement_id !== null) {
            throw new DomainException('Bir ters stok hareketi tekrar terslenemez.');
        }

        $existingReversal = StockMovement::query()
            ->where('reversal_of_movement_id', $originalMovementId)
            ->first();
        if ($existingReversal instanceof StockMovement
            && ($existingReversal->source_type !== $sourceEffect->sourceType
                || $existingReversal->source_id !== $sourceEffect->sourceId
                || $existingReversal->effect_type !== $sourceEffect->effectType)) {
            throw new DomainException('Stok hareketi daha önce ters kayıt ile kapatılmış.');
        }

        return $this->poster->post(new PostStockMovementData(
            sourceEffect: $sourceEffect,
            productId: (int) $original->product_id,
            warehouseId: (int) $original->warehouse_id,
            locationId: (int) $original->location_id,
            movementType: str_starts_with((string) $original->quantity_delta, '-')
                ? StockMovementType::AdjustmentIn
                : StockMovementType::AdjustmentOut,
            quantity: ltrim((string) $original->quantity_delta, '-'),
            unitCost: (string) $original->unit_cost,
            note: $note,
            reversalOfMovementId: (int) $original->getKey(),
        ));
    }
}
