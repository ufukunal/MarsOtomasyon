<?php

namespace App\Modules\Production\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Production\Models\ProductionLoss;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOrderMaterial;
use App\Modules\Production\Models\ProductionRecipe;
use App\Modules\Production\Models\ProductionRecipeLine;
use App\Modules\Products\Models\Product;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final readonly class ProductionOperations
{
    public function __construct(
        private StockMovementPoster $stockPoster,
        private Clock $clock,
    ) {}

    /**
     * @param  list<array{product_id:int,quantity:string}>  $materials
     */
    public function createRecipe(
        int $companyId,
        int $productId,
        string $code,
        string $name,
        string $outputQuantity,
        array $materials,
        ?string $note = null,
    ): ProductionRecipe {
        $code = $this->requiredText($code, 64, 'Reçete kodu');
        $name = $this->requiredText($name, 160, 'Reçete adı');
        $outputQuantity = $this->positiveDecimal($outputQuantity, 'Reçete çıktı miktarı');
        $note = $this->nullableText($note, 500);

        if ($materials === []) {
            throw new InvalidArgumentException('Reçetede en az bir malzeme satırı olmalıdır.');
        }

        return DB::transaction(function () use ($companyId, $productId, $code, $name, $outputQuantity, $materials, $note): ProductionRecipe {
            $this->companyProduct($companyId, $productId);
            $normalized = [];

            foreach ($materials as $material) {
                $materialProductId = (int) $material['product_id'];
                if ($materialProductId <= 0) {
                    throw new InvalidArgumentException('Reçete malzeme ürünü zorunludur.');
                }
                if ($materialProductId === $productId) {
                    throw new DomainException('Mamul ürün kendi reçetesinde malzeme olamaz.');
                }
                if (isset($normalized[$materialProductId])) {
                    throw new DomainException('Aynı malzeme reçetede birden fazla satırda kullanılamaz.');
                }

                $this->companyProduct($companyId, $materialProductId);
                $normalized[$materialProductId] = $this->positiveDecimal(
                    (string) $material['quantity'],
                    'Reçete malzeme miktarı',
                );
            }

            $recipe = ProductionRecipe::query()->create([
                'company_id' => $companyId,
                'product_id' => $productId,
                'code' => $code,
                'name' => $name,
                'output_quantity' => $outputQuantity,
                'is_active' => true,
                'note' => $note,
            ]);

            foreach ($normalized as $materialProductId => $quantity) {
                ProductionRecipeLine::query()->create([
                    'company_id' => $companyId,
                    'recipe_id' => $this->id($recipe),
                    'material_product_id' => $materialProductId,
                    'quantity_per_batch' => $quantity,
                ]);
            }

            return $recipe->refresh()->load('lines');
        }, 3);
    }

    public function createOrder(
        int $companyId,
        int $recipeId,
        string $orderNo,
        string $plannedQuantity,
        int $warehouseId,
        int $locationId,
        ?string $note = null,
    ): ProductionOrder {
        $orderNo = $this->requiredText($orderNo, 64, 'Üretim emri numarası');
        $plannedQuantity = $this->positiveDecimal($plannedQuantity, 'Planlanan üretim miktarı');
        $note = $this->nullableText($note, 500);

        return DB::transaction(function () use ($companyId, $recipeId, $orderNo, $plannedQuantity, $warehouseId, $locationId, $note): ProductionOrder {
            $recipe = ProductionRecipe::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($recipeId);

            if (! $recipe->is_active) {
                throw new DomainException('Pasif reçeteden üretim emri oluşturulamaz.');
            }

            $this->warehouseLocation($companyId, $warehouseId, $locationId);
            $lines = ProductionRecipeLine::query()
                ->where('company_id', $companyId)
                ->where('recipe_id', $recipeId)
                ->orderBy('id')
                ->get();

            if ($lines->isEmpty()) {
                throw new LogicException('Aktif üretim reçetesinin malzeme satırı bulunamadı.');
            }

            $order = ProductionOrder::query()->create([
                'company_id' => $companyId,
                'recipe_id' => $recipeId,
                'product_id' => (int) $recipe->product_id,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'order_no' => $orderNo,
                'status' => 'draft',
                'planned_quantity' => $plannedQuantity,
                'material_cost' => '0.000000',
                'loss_cost' => '0.000000',
                'output_quantity' => '0.000000',
                'output_unit_cost' => '0.000000',
                'output_value' => '0.000000',
                'note' => $note,
            ]);

            foreach ($lines as $line) {
                $requiredQuantity = $this->multiplyDivide(
                    $plannedQuantity,
                    (string) $line->quantity_per_batch,
                    (string) $recipe->output_quantity,
                    'Üretim malzeme ihtiyacı',
                );

                ProductionOrderMaterial::query()->create([
                    'company_id' => $companyId,
                    'production_order_id' => $this->id($order),
                    'product_id' => (int) $line->material_product_id,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'required_quantity' => $requiredQuantity,
                    'issued_quantity' => '0.000000',
                    'issued_value' => '0.000000',
                ]);
            }

            $this->event($order, 'created', [
                'recipe_id' => $recipeId,
                'planned_quantity' => $plannedQuantity,
            ]);

            return $order->refresh()->load('materials');
        }, 3);
    }

    public function issueMaterials(int $companyId, int $productionOrderId): ProductionOrder
    {
        return DB::transaction(function () use ($companyId, $productionOrderId): ProductionOrder {
            $order = $this->lockOrder($companyId, $productionOrderId);

            if ($order->material_issued_at !== null) {
                return $order->refresh()->load('materials');
            }
            if ($order->status !== 'draft') {
                throw new DomainException('Malzeme çıkışı yalnız taslak üretim emrinde yapılabilir.');
            }

            $materials = ProductionOrderMaterial::query()
                ->where('company_id', $companyId)
                ->where('production_order_id', $productionOrderId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($materials->isEmpty()) {
                throw new LogicException('Üretim emri malzeme snapshotı bulunamadı.');
            }

            $materialCost = '0.000000';
            foreach ($materials as $material) {
                $posting = $this->stockPoster->post(new PostStockMovementData(
                    sourceEffect: new SourceEffectIdentity(
                        $companyId,
                        'production.material',
                        (string) $this->id($material),
                        'production.material_issue',
                    ),
                    productId: (int) $material->product_id,
                    warehouseId: (int) $material->warehouse_id,
                    locationId: (int) $material->location_id,
                    movementType: StockMovementType::ProductionMaterialOut,
                    quantity: (string) $material->required_quantity,
                    note: 'Üretim emri '.$order->order_no.' malzeme çıkışı',
                ));

                $issuedValue = $this->absoluteDecimal((string) $posting->movement->value_delta);
                $material->update([
                    'issued_quantity' => (string) $material->required_quantity,
                    'issued_value' => $issuedValue,
                    'stock_movement_id' => $this->id($posting->movement),
                ]);
                $materialCost = $this->add($materialCost, $issuedValue);
            }

            $order->update([
                'status' => 'in_progress',
                'material_cost' => $materialCost,
                'material_issued_at' => $this->clock->now(),
            ]);
            $this->event($order, 'materials_issued', ['material_cost' => $materialCost]);

            return $order->refresh()->load('materials');
        }, 3);
    }

    public function recordLoss(
        int $companyId,
        int $productionOrderId,
        string $operationKey,
        int $productId,
        string $quantity,
        string $lossType,
        ?string $note = null,
    ): ProductionLoss {
        $operationKey = $this->requiredText($operationKey, 64, 'Fire/eksik işlem anahtarı');
        $quantity = $this->positiveDecimal($quantity, 'Fire/eksik miktarı');
        $lossType = trim($lossType);
        $note = $this->nullableText($note, 240);

        if (! in_array($lossType, ['fire', 'missing'], true)) {
            throw new InvalidArgumentException('Fire/eksik türü fire veya missing olmalıdır.');
        }

        return DB::transaction(function () use ($companyId, $productionOrderId, $operationKey, $productId, $quantity, $lossType, $note): ProductionLoss {
            $order = $this->lockOrder($companyId, $productionOrderId);
            if ($order->status !== 'in_progress' || $order->received_at !== null) {
                throw new DomainException('Fire/eksik yalnız malzeme çıkışı yapılmış ve mamul girişi yapılmamış üretim emrinde kaydedilebilir.');
            }

            $existing = ProductionLoss::query()
                ->where('company_id', $companyId)
                ->where('operation_key', $operationKey)
                ->first();
            if ($existing instanceof ProductionLoss) {
                if ((int) $existing->production_order_id !== $productionOrderId
                    || (int) $existing->product_id !== $productId
                    || (string) $existing->quantity !== $quantity
                    || (string) $existing->loss_type !== $lossType
                    || $existing->note !== $note) {
                    throw new DomainException('Aynı fire/eksik işlem anahtarı farklı payload ile tekrar kullanılamaz.');
                }

                return $existing;
            }

            $materialExists = ProductionOrderMaterial::query()
                ->where('company_id', $companyId)
                ->where('production_order_id', $productionOrderId)
                ->where('product_id', $productId)
                ->exists();
            if (! $materialExists) {
                throw new DomainException('Fire/eksik yalnız üretim emrinin reçete malzemelerinden kaydedilebilir.');
            }

            $now = $this->clock->now();
            $loss = ProductionLoss::query()->create([
                'company_id' => $companyId,
                'production_order_id' => $productionOrderId,
                'operation_key' => $operationKey,
                'product_id' => $productId,
                'warehouse_id' => (int) $order->warehouse_id,
                'location_id' => (int) $order->location_id,
                'loss_type' => $lossType,
                'quantity' => $quantity,
                'carrying_value' => '0.000000',
                'note' => $note,
                'occurred_at' => $now,
                'created_at' => $now,
            ]);

            $posting = $this->stockPoster->post(new PostStockMovementData(
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'production.loss',
                    (string) $this->id($loss),
                    'production.loss_out',
                ),
                productId: $productId,
                warehouseId: (int) $order->warehouse_id,
                locationId: (int) $order->location_id,
                movementType: StockMovementType::ProductionLossOut,
                quantity: $quantity,
                note: 'Üretim emri '.$order->order_no.' '.($lossType === 'fire' ? 'fire' : 'eksik').' kaydı',
            ));

            $carryingValue = $this->absoluteDecimal((string) $posting->movement->value_delta);
            $loss->update([
                'carrying_value' => $carryingValue,
                'stock_movement_id' => $this->id($posting->movement),
            ]);

            $lossCost = $this->sumLossCost($companyId, $productionOrderId);
            $order->update(['loss_cost' => $lossCost]);
            $this->event($order, 'loss_recorded', [
                'loss_id' => $this->id($loss),
                'loss_type' => $lossType,
                'quantity' => $quantity,
                'carrying_value' => $carryingValue,
            ]);

            return $loss->refresh();
        }, 3);
    }

    public function receiveOutput(int $companyId, int $productionOrderId): ProductionOrder
    {
        return DB::transaction(function () use ($companyId, $productionOrderId): ProductionOrder {
            $order = $this->lockOrder($companyId, $productionOrderId);

            if ($order->received_at !== null) {
                return $order->refresh();
            }
            if ($order->status !== 'in_progress' || $order->material_issued_at === null) {
                throw new DomainException('Mamul girişi için önce malzeme çıkışı tamamlanmalıdır.');
            }

            $totalCost = $this->add((string) $order->material_cost, (string) $order->loss_cost);
            if ($this->isZero($totalCost)) {
                throw new LogicException('Üretim maliyeti sıfır olamaz.');
            }
            $unitCost = $this->divide($totalCost, (string) $order->planned_quantity, 'Mamul birim maliyeti');

            $posting = $this->stockPoster->post(new PostStockMovementData(
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'production.order',
                    (string) $productionOrderId,
                    'production.finished_goods_receipt',
                ),
                productId: (int) $order->product_id,
                warehouseId: (int) $order->warehouse_id,
                locationId: (int) $order->location_id,
                movementType: StockMovementType::ProductionReceiptIn,
                quantity: (string) $order->planned_quantity,
                unitCost: $unitCost,
                note: 'Üretim emri '.$order->order_no.' mamul girişi',
                carryingValue: $totalCost,
            ));

            $order->update([
                'status' => 'received',
                'output_quantity' => (string) $order->planned_quantity,
                'output_unit_cost' => $unitCost,
                'output_value' => $totalCost,
                'output_stock_movement_id' => $this->id($posting->movement),
                'received_at' => $this->clock->now(),
            ]);
            $this->event($order, 'finished_goods_received', [
                'quantity' => (string) $order->planned_quantity,
                'unit_cost' => $unitCost,
                'value' => $totalCost,
            ]);

            return $order->refresh();
        }, 3);
    }

    public function complete(int $companyId, int $productionOrderId): ProductionOrder
    {
        return DB::transaction(function () use ($companyId, $productionOrderId): ProductionOrder {
            $order = $this->lockOrder($companyId, $productionOrderId);
            if ($order->status === 'completed') {
                return $order;
            }
            if ($order->status !== 'received' || $order->received_at === null || $order->output_stock_movement_id === null) {
                throw new DomainException('Üretim emri mamul girişi tamamlanmadan kapatılamaz.');
            }

            $order->update([
                'status' => 'completed',
                'completed_at' => $this->clock->now(),
            ]);
            $this->event($order, 'completed', [
                'output_quantity' => (string) $order->output_quantity,
                'output_value' => (string) $order->output_value,
            ]);

            return $order->refresh();
        }, 3);
    }

    private function lockOrder(int $companyId, int $productionOrderId): ProductionOrder
    {
        return ProductionOrder::query()
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->findOrFail($productionOrderId);
    }

    private function companyProduct(int $companyId, int $productId): Product
    {
        return Product::query()->where('company_id', $companyId)->findOrFail($productId);
    }

    private function warehouseLocation(int $companyId, int $warehouseId, int $locationId): void
    {
        Warehouse::query()->where('company_id', $companyId)->findOrFail($warehouseId);
        WarehouseLocation::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->findOrFail($locationId);
    }

    /** @param array<string, int|string|null> $payload */
    private function event(ProductionOrder $order, string $eventType, array $payload): void
    {
        $now = $this->clock->now();
        DB::table('production_events')->insert([
            'company_id' => $order->company_id,
            'production_order_id' => $this->id($order),
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => $now,
            'created_at' => $now,
        ]);
    }

    private function sumLossCost(int $companyId, int $productionOrderId): string
    {
        $row = DB::selectOne(
            'SELECT COALESCE(round(sum(carrying_value), 6), 0)::text AS value FROM production_losses WHERE company_id = ? AND production_order_id = ?',
            [$companyId, $productionOrderId],
        );

        return $this->nonNegativeDecimal((string) $row->value, 'Toplam fire/eksik maliyeti');
    }

    private function multiplyDivide(string $left, string $right, string $divisor, string $label): string
    {
        $row = DB::selectOne(
            'SELECT round(CAST(? AS numeric) * CAST(? AS numeric) / CAST(? AS numeric), 6)::text AS value',
            [$left, $right, $divisor],
        );

        return $this->positiveDecimal((string) $row->value, $label);
    }

    private function divide(string $value, string $divisor, string $label): string
    {
        $row = DB::selectOne(
            'SELECT round(CAST(? AS numeric) / CAST(? AS numeric), 6)::text AS value',
            [$value, $divisor],
        );

        return $this->positiveDecimal((string) $row->value, $label);
    }

    private function add(string $left, string $right): string
    {
        $row = DB::selectOne(
            'SELECT round(CAST(? AS numeric) + CAST(? AS numeric), 6)::text AS value',
            [$left, $right],
        );

        return $this->nonNegativeDecimal((string) $row->value, 'Üretim maliyeti');
    }

    private function absoluteDecimal(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, '-')) {
            $value = substr($value, 1);
        }

        return $this->positiveDecimal($value, 'Taşıma değeri');
    }

    private function positiveDecimal(string $value, string $label): string
    {
        $normalized = $this->nonNegativeDecimal($value, $label);
        if ($this->isZero($normalized)) {
            throw new InvalidArgumentException($label.' sıfırdan büyük olmalıdır.');
        }

        return $normalized;
    }

    private function nonNegativeDecimal(string $value, string $label): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw new InvalidArgumentException($label.' en fazla 6 ondalıklı pozitif decimal olmalıdır.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 14) {
            throw new InvalidArgumentException($label.' desteklenen decimal sınırını aşıyor.');
        }

        return $whole.'.'.str_pad($fraction, 6, '0');
    }

    private function isZero(string $value): bool
    {
        return $value === '0.000000';
    }

    private function requiredText(string $value, int $max, string $label): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException($label.' zorunludur.');
        }
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException($label.' en fazla '.$max.' karakter olabilir.');
        }

        return $value;
    }

    private function nullableText(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Metin en fazla '.$max.' karakter olabilir.');
        }

        return $value;
    }

    private function id(Model $model): int
    {
        $id = $model->getKey();

        return is_int($id) ? $id : throw new LogicException('Persisted model key must be integer.');
    }
}
