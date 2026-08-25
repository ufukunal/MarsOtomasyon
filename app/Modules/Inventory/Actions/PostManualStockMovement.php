<?php

namespace App\Modules\Inventory\Actions;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Inventory\Enums\ManualStockMovementKind;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use LogicException;

final readonly class PostManualStockMovement
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
        private Clock $clock,
    ) {}

    /** @throws JsonException */
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
        $quantity = trim($quantity);
        $unitCost = $unitCost === null ? null : trim($unitCost);
        $note = $note === null || trim($note) === '' ? null : trim($note);

        if ($operationKey === '' || strlen($operationKey) > 64) {
            throw ValidationException::withMessages([
                'operation_key' => 'Stok hareketi işlem anahtarı geçersiz.',
            ]);
        }

        $this->assertPositiveDecimal($quantity, 'quantity', 'Miktar sıfırdan büyük olmalıdır.');
        if ($kind->isInbound()) {
            if ($unitCost === null) {
                throw ValidationException::withMessages([
                    'unit_cost' => 'Pozitif stok girişinde birim maliyet zorunludur.',
                ]);
            }
            $this->assertPositiveDecimal($unitCost, 'unit_cost', 'Pozitif stok girişinde birim maliyet sıfırdan büyük olmalıdır.');
        }

        $fingerprint = hash('sha256', json_encode([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'kind' => $kind->value,
            'quantity' => $quantity,
            'unit_cost' => $kind->isInbound() ? $unitCost : null,
            'note' => $note,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $companyId,
            $operationKey,
            $fingerprint,
            $productId,
            $warehouseId,
            $locationId,
            $kind,
            $quantity,
            $unitCost,
            $note,
        ): StockMovement {
            $existing = StockMovement::query()
                ->where('company_id', $companyId)
                ->where('operation_key', $operationKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages([
                        'operation_key' => 'Aynı işlem anahtarı farklı stok hareketi verisiyle tekrar kullanılamaz.',
                    ]);
                }

                return $existing;
            }

            Product::query()
                ->where('company_id', $companyId)
                ->findOrFail($productId);
            Warehouse::query()
                ->where('company_id', $companyId)
                ->findOrFail($warehouseId);
            WarehouseLocation::query()
                ->where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->findOrFail($locationId);

            $now = $this->clock->now();
            DB::table('stock_balances')->insertOrIgnore([
                'company_id' => $companyId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'quantity' => '0.000000',
                'average_unit_cost' => '0.000000',
                'inventory_value' => '0.000000',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $balance = StockBalance::query()
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($kind->isInbound()) {
                $effectiveUnitCost = (string) $unitCost;
                $valueDelta = $this->multiply($quantity, $effectiveUnitCost);
                $row = DB::selectOne(<<<'SQL'
                    UPDATE stock_balances
                    SET quantity = quantity + CAST(? AS numeric),
                        inventory_value = inventory_value + CAST(? AS numeric),
                        average_unit_cost = (inventory_value + CAST(? AS numeric)) / (quantity + CAST(? AS numeric)),
                        updated_at = ?
                    WHERE id = ?
                    RETURNING quantity::text AS quantity,
                              average_unit_cost::text AS average_unit_cost,
                              inventory_value::text AS inventory_value
                    SQL, [$quantity, $valueDelta, $valueDelta, $quantity, $now, $balance->getKey()]);
            } else {
                $effectiveUnitCost = (string) $balance->average_unit_cost;
                $absoluteValue = $this->multiply($quantity, $effectiveUnitCost);
                $valueDelta = $this->negate($absoluteValue);
                $row = DB::selectOne(<<<'SQL'
                    UPDATE stock_balances
                    SET quantity = quantity - CAST(? AS numeric),
                        inventory_value = CASE
                            WHEN quantity - CAST(? AS numeric) = 0 THEN 0
                            ELSE inventory_value - CAST(? AS numeric)
                        END,
                        average_unit_cost = CASE
                            WHEN quantity - CAST(? AS numeric) = 0 THEN 0
                            ELSE average_unit_cost
                        END,
                        updated_at = ?
                    WHERE id = ?
                      AND quantity >= CAST(? AS numeric)
                    RETURNING quantity::text AS quantity,
                              average_unit_cost::text AS average_unit_cost,
                              inventory_value::text AS inventory_value
                    SQL, [$quantity, $quantity, $absoluteValue, $quantity, $now, $balance->getKey(), $quantity]);

                if ($row === null) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Negatif stok yasaktır. Çıkış miktarı mevcut fiziksel stok miktarını aşamaz.',
                    ]);
                }
            }

            if ($row === null) {
                throw new LogicException('Stock balance projection update did not return a row.');
            }

            $quantityDelta = $kind->isInbound() ? $quantity : $this->negate($quantity);
            $movement = StockMovement::query()->create([
                'company_id' => $companyId,
                'operation_key' => $operationKey,
                'request_fingerprint' => $fingerprint,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'movement_type' => $kind,
                'quantity_delta' => $quantityDelta,
                'unit_cost' => $effectiveUnitCost,
                'value_delta' => $valueDelta,
                'balance_quantity_after' => (string) $row->quantity,
                'average_unit_cost_after' => (string) $row->average_unit_cost,
                'inventory_value_after' => (string) $row->inventory_value,
                'note' => $note,
                'occurred_at' => $now,
                'created_at' => $now,
            ]);

            $this->audit->record(
                AuditAction::StockMovementPosted,
                AuditTargetType::StockMovement,
                $movement->getKey(),
                after: [
                    'movement_type' => $kind->value,
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'quantity_delta' => $quantityDelta,
                    'unit_cost' => $effectiveUnitCost,
                    'value_delta' => $valueDelta,
                    'balance_quantity_after' => (string) $row->quantity,
                    'average_unit_cost_after' => (string) $row->average_unit_cost,
                ],
            );

            return $movement;
        });
    }

    private function assertPositiveDecimal(string $value, string $field, string $message): void
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) > 0 AS valid', [$value]);
        if ($row === null || $row->valid !== true) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function multiply(string $left, string $right): string
    {
        $row = DB::selectOne('SELECT (CAST(? AS numeric) * CAST(? AS numeric))::text AS value', [$left, $right]);

        return $row === null
            ? throw new LogicException('Numeric multiplication did not return a value.')
            : (string) $row->value;
    }

    private function negate(string $value): string
    {
        $row = DB::selectOne('SELECT (-CAST(? AS numeric))::text AS value', [$value]);

        return $row === null
            ? throw new LogicException('Numeric negation did not return a value.')
            : (string) $row->value;
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();

        return is_int($companyId)
            ? $companyId
            : throw new LogicException('Stock movement posting requires a persisted active company.');
    }
}
