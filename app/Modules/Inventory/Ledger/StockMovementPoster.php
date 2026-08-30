<?php

namespace App\Modules\Inventory\Ledger;

use App\Foundation\Clock\Clock;
use App\Foundation\Idempotency\IdempotencyStatus;
use App\Foundation\Idempotency\IdempotencyStore;
use App\Foundation\Idempotency\RequestFingerprint;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class StockMovementPoster
{
    private const IDEMPOTENCY_SCOPE = 'inventory.stock_effect';

    public function __construct(
        private IdempotencyStore $idempotency,
        private Clock $clock,
    ) {}

    public function post(PostStockMovementData $data): StockMovementPostingResult
    {
        $this->assertInsideTransaction();

        $companyId = $data->sourceEffect->companyId;
        $quantity = $this->positiveDecimal($data->quantity, 'quantity', 'Miktar sıfırdan büyük olmalıdır.');
        $note = $this->normalizeNote($data->note);
        $original = $this->reversalTarget($data, $companyId, $quantity);

        $unitCost = null;
        if ($original instanceof StockMovement) {
            $unitCost = (string) $original->unit_cost;
        } elseif ($data->movementType->isInbound()) {
            if ($data->unitCost === null) {
                throw ValidationException::withMessages([
                    'unit_cost' => 'Pozitif stok girişinde birim maliyet zorunludur.',
                ]);
            }
            $unitCost = $this->positiveDecimal(
                $data->unitCost,
                'unit_cost',
                'Pozitif stok girişinde birim maliyet sıfırdan büyük olmalıdır.',
            );
        }

        $carryingValue = $this->normalizeExplicitCarryingValue($data, $original);

        $effectKey = $data->sourceEffect->fingerprint();
        $fingerprint = RequestFingerprint::fromPayload([
            'company_id' => $companyId,
            'product_id' => $data->productId,
            'warehouse_id' => $data->warehouseId,
            'location_id' => $data->locationId,
            'movement_type' => $data->movementType->value,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'carrying_value' => $carryingValue,
            'reversal_of_movement_id' => $data->reversalOfMovementId,
            'note' => $note,
        ]);
        $claim = $this->idempotency->claim(self::IDEMPOTENCY_SCOPE, $effectKey, $fingerprint);

        if ($claim->isReplay()) {
            if ($claim->status !== IdempotencyStatus::Completed) {
                throw new LogicException('Stok effect idempotency kaydı tamamlanmamış durumda bırakılamaz.');
            }

            $existing = StockMovement::query()
                ->where('company_id', $companyId)
                ->where('source_type', $data->sourceEffect->sourceType)
                ->where('source_id', $data->sourceEffect->sourceId)
                ->where('effect_type', $data->sourceEffect->effectType)
                ->first();

            if (! $existing instanceof StockMovement) {
                throw new LogicException('Tamamlanmış stok effect idempotency kaydının ledger satırı bulunamadı.');
            }

            return new StockMovementPostingResult($existing, true);
        }

        Product::query()
            ->where('company_id', $companyId)
            ->findOrFail($data->productId);
        Warehouse::query()
            ->where('company_id', $companyId)
            ->findOrFail($data->warehouseId);
        WarehouseLocation::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $data->warehouseId)
            ->findOrFail($data->locationId);

        $now = $this->clock->now();
        DB::table('stock_balances')->insertOrIgnore([
            'company_id' => $companyId,
            'product_id' => $data->productId,
            'warehouse_id' => $data->warehouseId,
            'location_id' => $data->locationId,
            'quantity' => '0.000000',
            'average_unit_cost' => '0.000000',
            'inventory_value' => '0.000000',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $balance = StockBalance::query()
            ->where('company_id', $companyId)
            ->where('product_id', $data->productId)
            ->where('warehouse_id', $data->warehouseId)
            ->where('location_id', $data->locationId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($data->movementType->isInbound()) {
            $effectiveUnitCost = (string) $unitCost;
            $valueDelta = $original instanceof StockMovement
                ? $this->absolute((string) $original->value_delta)
                : ($carryingValue ?? $this->multiply($quantity, $effectiveUnitCost));
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
        } elseif ($original instanceof StockMovement) {
            $effectiveUnitCost = (string) $original->unit_cost;
            $absoluteValue = $this->absolute((string) $original->value_delta);
            $valueDelta = $this->negate($absoluteValue);
            $reversalSql = <<<'SQL'
                UPDATE stock_balances
                SET quantity = quantity - CAST(? AS numeric),
                    inventory_value = inventory_value - CAST(? AS numeric),
                    average_unit_cost = CASE
                        WHEN quantity - CAST(? AS numeric) = 0 THEN 0
                        ELSE (inventory_value - CAST(? AS numeric)) / (quantity - CAST(? AS numeric))
                    END,
                    updated_at = ?
                WHERE id = ?
                  AND available_quantity >= CAST(? AS numeric)
                  AND inventory_value >= CAST(? AS numeric)
                  AND (
                      (quantity - CAST(? AS numeric) = 0 AND inventory_value - CAST(? AS numeric) = 0)
                      OR
                      (quantity - CAST(? AS numeric) > 0 AND inventory_value - CAST(? AS numeric) > 0)
                  )
                RETURNING quantity::text AS quantity,
                          average_unit_cost::text AS average_unit_cost,
                          inventory_value::text AS inventory_value
                SQL;
            $reversalBindings = [
                $quantity,
                $absoluteValue,
                $quantity,
                $absoluteValue,
                $quantity,
                $now,
                $balance->getKey(),
                $quantity,
                $absoluteValue,
                $quantity,
                $absoluteValue,
                $quantity,
                $absoluteValue,
            ];
            $row = DB::selectOne($reversalSql, $reversalBindings);

            if ($row === null) {
                throw ValidationException::withMessages([
                    'quantity' => 'Ters kayıt, orijinal miktar ve taşıma değerini mevcut kullanılabilir stoktan güvenle çıkaramıyor.',
                ]);
            }
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
                  AND available_quantity >= CAST(? AS numeric)
                RETURNING quantity::text AS quantity,
                          average_unit_cost::text AS average_unit_cost,
                          inventory_value::text AS inventory_value
                SQL, [$quantity, $quantity, $absoluteValue, $quantity, $now, $balance->getKey(), $quantity]);

            if ($row === null) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok çıkışı kullanılabilir miktarı aşamaz. Rezerve veya bloke miktar fiziksel çıkışla tüketilemez.',
                ]);
            }
        }

        if ($row === null) {
            throw new LogicException('Stock balance projection update did not return a row.');
        }

        $quantityDelta = $data->movementType->isInbound() ? $quantity : $this->negate($quantity);
        $movement = StockMovement::query()->create([
            'company_id' => $companyId,
            'operation_key' => $effectKey,
            'request_fingerprint' => $fingerprint->value,
            'source_type' => $data->sourceEffect->sourceType,
            'source_id' => $data->sourceEffect->sourceId,
            'effect_type' => $data->sourceEffect->effectType,
            'reversal_of_movement_id' => $data->reversalOfMovementId,
            'product_id' => $data->productId,
            'warehouse_id' => $data->warehouseId,
            'location_id' => $data->locationId,
            'movement_type' => $data->movementType,
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

        $this->idempotency->complete($claim);

        return new StockMovementPostingResult($movement, false);
    }

    private function reversalTarget(PostStockMovementData $data, int $companyId, string $quantity): ?StockMovement
    {
        if ($data->reversalOfMovementId === null) {
            return null;
        }

        $original = StockMovement::query()
            ->where('company_id', $companyId)
            ->whereKey($data->reversalOfMovementId)
            ->sharedLock()
            ->first();

        if (! $original instanceof StockMovement) {
            throw new DomainException('Ters stok hareketi hedefi bulunamadı.');
        }
        if ($original->reversal_of_movement_id !== null) {
            throw new DomainException('Bir ters stok hareketi tekrar terslenemez.');
        }
        if ((int) $original->product_id !== $data->productId
            || (int) $original->warehouse_id !== $data->warehouseId
            || (int) $original->location_id !== $data->locationId) {
            throw new DomainException('Ters stok hareketi orijinal hareket ile aynı stok scope üzerinde olmalıdır.');
        }

        $expectedType = str_starts_with((string) $original->quantity_delta, '-')
            ? StockMovementType::AdjustmentIn
            : StockMovementType::AdjustmentOut;
        if ($data->movementType !== $expectedType) {
            throw new DomainException('Ters stok hareketinin yönü orijinal hareketin tam tersi olmalıdır.');
        }

        if ($quantity !== $this->absolute((string) $original->quantity_delta)) {
            throw ValidationException::withMessages([
                'quantity' => 'Ters kayıt miktarı orijinal stok hareketi miktarı ile aynı olmalıdır.',
            ]);
        }

        return $original;
    }

    private function normalizeExplicitCarryingValue(PostStockMovementData $data, ?StockMovement $original): ?string
    {
        if ($data->carryingValue === null) {
            return null;
        }
        if ($original instanceof StockMovement || ! in_array($data->movementType, [StockMovementType::TransferIn, StockMovementType::ProductionReceiptIn, StockMovementType::SubcontractReceiptIn], true)) {
            throw ValidationException::withMessages([
                'carrying_value' => 'Açık taşıma değeri yalnız doğrulanmış taşıma maliyetli stok girişlerinde kullanılabilir.',
            ]);
        }

        return $this->positiveDecimal(
            $data->carryingValue,
            'carrying_value',
            'Taşıma değeri sıfırdan büyük olmalıdır.',
        );
    }

    private function positiveDecimal(string $value, string $field, string $message): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages([$field => $message]);
        }

        $integerPart = explode('.', $value, 2)[0];
        if (strlen(ltrim($integerPart, '0')) > 14) {
            throw ValidationException::withMessages([$field => $message]);
        }

        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) > 0 AS valid',
            [$value, $value],
        );
        if ($row === null || $row->valid !== true) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return (string) $row->value;
    }

    private function multiply(string $left, string $right): string
    {
        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) * CAST(? AS numeric) AS numeric(20,6))::text AS value',
            [$left, $right],
        );

        return $row === null
            ? throw new LogicException('Numeric multiplication did not return a value.')
            : (string) $row->value;
    }

    private function absolute(string $value): string
    {
        $row = DB::selectOne('SELECT abs(CAST(? AS numeric))::text AS value', [$value]);

        return $row === null
            ? throw new LogicException('Numeric absolute did not return a value.')
            : (string) $row->value;
    }

    private function negate(string $value): string
    {
        $row = DB::selectOne('SELECT (-CAST(? AS numeric))::text AS value', [$value]);

        return $row === null
            ? throw new LogicException('Numeric negation did not return a value.')
            : (string) $row->value;
    }

    private function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $note = trim($note);
        if ($note === '') {
            return null;
        }
        if (mb_strlen($note) > 240) {
            throw ValidationException::withMessages(['note' => 'Stok hareketi notu en fazla 240 karakter olabilir.']);
        }

        return $note;
    }

    private function assertInsideTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Stok effect posting aynı business transaction içinde çalışmalıdır.');
        }
    }
}
