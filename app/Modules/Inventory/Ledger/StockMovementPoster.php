<?php

namespace App\Modules\Inventory\Ledger;

use App\Foundation\Clock\Clock;
use App\Foundation\Idempotency\IdempotencyStatus;
use App\Foundation\Idempotency\IdempotencyStore;
use App\Foundation\Idempotency\RequestFingerprint;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
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
        $unitCost = null;
        if ($data->movementType->isInbound()) {
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

        $effectKey = $data->sourceEffect->fingerprint();
        $fingerprint = RequestFingerprint::fromPayload([
            'company_id' => $companyId,
            'product_id' => $data->productId,
            'warehouse_id' => $data->warehouseId,
            'location_id' => $data->locationId,
            'movement_type' => $data->movementType->value,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
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

        $quantityDelta = $data->movementType->isInbound() ? $quantity : $this->negate($quantity);
        $movement = StockMovement::query()->create([
            'company_id' => $companyId,
            'operation_key' => $effectKey,
            'request_fingerprint' => $fingerprint->value,
            'source_type' => $data->sourceEffect->sourceType,
            'source_id' => $data->sourceEffect->sourceId,
            'effect_type' => $data->sourceEffect->effectType,
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
