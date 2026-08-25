<?php

namespace App\Modules\Inventory\Actions;

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Inventory\Enums\ManualStockMovementKind;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class PostManualStockMovement
{
    private const SOURCE_TYPE = 'inventory.manual_stock';

    public function __construct(
        private ActiveCompanyContext $companyContext,
        private StockMovementPoster $poster,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        string $operationKey,
        int $productId,
        int $warehouseId,
        int $locationId,
        ManualStockMovementKind $kind,
        string $quantity,
        ?string $unitCost,
        ?string $note,
    ): StockMovement {
        $companyId = $this->companyId();
        $operationKey = trim($operationKey);
        if ($operationKey === '' || mb_strlen($operationKey) > 64) {
            throw ValidationException::withMessages([
                'operation_key' => 'Stok hareketi işlem anahtarı geçersiz.',
            ]);
        }

        return DB::transaction(function () use (
            $companyId,
            $operationKey,
            $productId,
            $warehouseId,
            $locationId,
            $kind,
            $quantity,
            $unitCost,
            $note,
        ): StockMovement {
            $result = $this->poster->post(new PostStockMovementData(
                sourceEffect: new SourceEffectIdentity(
                    companyId: $companyId,
                    sourceType: self::SOURCE_TYPE,
                    sourceId: $operationKey,
                    effectType: 'inventory.'.$kind->value,
                ),
                productId: $productId,
                warehouseId: $warehouseId,
                locationId: $locationId,
                movementType: $kind->ledgerType(),
                quantity: $quantity,
                unitCost: $unitCost,
                note: $note,
            ));

            if (! $result->replayed) {
                $movement = $result->movement;
                $this->audit->record(
                    AuditAction::StockMovementPosted,
                    AuditTargetType::StockMovement,
                    $movement->getKey(),
                    after: [
                        'source_type' => $movement->source_type,
                        'source_id' => $movement->source_id,
                        'effect_type' => $movement->effect_type,
                        'movement_type' => $movement->movement_type->value,
                        'product_id' => $movement->product_id,
                        'warehouse_id' => $movement->warehouse_id,
                        'location_id' => $movement->location_id,
                        'quantity_delta' => $movement->quantity_delta,
                        'unit_cost' => $movement->unit_cost,
                        'value_delta' => $movement->value_delta,
                        'balance_quantity_after' => $movement->balance_quantity_after,
                        'average_unit_cost_after' => $movement->average_unit_cost_after,
                    ],
                );
            }

            return $result->movement;
        });
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();

        return is_int($companyId)
            ? $companyId
            : throw new LogicException('Stock movement posting requires a persisted active company.');
    }
}
