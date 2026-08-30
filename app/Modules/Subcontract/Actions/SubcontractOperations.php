<?php

namespace App\Modules\Subcontract\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Models\Account;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use App\Modules\Subcontract\Models\SubcontractEvent;
use App\Modules\Subcontract\Models\SubcontractLoss;
use App\Modules\Subcontract\Models\SubcontractOrder;
use App\Modules\Subcontract\Models\SubcontractOrderMaterial;
use App\Modules\Subcontract\Models\SubcontractReceipt;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final readonly class SubcontractOperations
{
    public function __construct(private StockMovementPoster $stockPoster, private Clock $clock) {}

    /** @param list<array{product_id:int,quantity:string}> $materials */
    public function createOrder(int $companyId, int $supplierAccountId, int $outputProductId, string $orderNo, string $plannedOutputQuantity, int $warehouseId, int $locationId, array $materials, ?string $note = null): SubcontractOrder
    {
        $orderNo = $this->requiredText($orderNo, 64, 'Fason sipariş numarası');
        $plannedOutputQuantity = $this->positiveDecimal($plannedOutputQuantity, 'Planlanan fason çıktı miktarı');
        $note = $this->nullableText($note, 500);
        if ($materials === []) {
            throw new InvalidArgumentException('Fason siparişte en az bir malzeme olmalıdır.');
        }

        return DB::transaction(function () use ($companyId, $supplierAccountId, $outputProductId, $orderNo, $plannedOutputQuantity, $warehouseId, $locationId, $materials, $note): SubcontractOrder {
            Account::query()->where('company_id', $companyId)->where('status', 'active')->whereIn('type', ['supplier', 'mixed'])->findOrFail($supplierAccountId);
            Product::query()->where('company_id', $companyId)->findOrFail($outputProductId);
            Warehouse::query()->where('company_id', $companyId)->findOrFail($warehouseId);
            WarehouseLocation::query()->where('company_id', $companyId)->where('warehouse_id', $warehouseId)->findOrFail($locationId);

            $normalized = [];
            foreach ($materials as $material) {
                $productId = (int) $material['product_id'];
                if ($productId < 1 || isset($normalized[$productId])) {
                    throw new InvalidArgumentException('Fason malzeme satırları benzersiz ve geçerli olmalıdır.');
                }
                Product::query()->where('company_id', $companyId)->findOrFail($productId);
                $normalized[$productId] = $this->positiveDecimal((string) $material['quantity'], 'Fason malzeme miktarı');
            }

            $order = SubcontractOrder::query()->create([
                'company_id' => $companyId, 'supplier_account_id' => $supplierAccountId, 'output_product_id' => $outputProductId,
                'warehouse_id' => $warehouseId, 'location_id' => $locationId, 'order_no' => $orderNo, 'status' => 'draft',
                'planned_output_quantity' => $plannedOutputQuantity, 'sent_value' => '0.000000', 'loss_value' => '0.000000',
                'received_output_quantity' => '0.000000', 'received_output_value' => '0.000000', 'note' => $note,
            ]);
            foreach ($normalized as $productId => $quantity) {
                SubcontractOrderMaterial::query()->create([
                    'company_id' => $companyId, 'subcontract_order_id' => $this->id($order), 'product_id' => $productId,
                    'planned_quantity' => $quantity, 'sent_quantity' => '0.000000', 'sent_value' => '0.000000',
                    'consumed_quantity' => '0.000000', 'consumed_value' => '0.000000', 'loss_quantity' => '0.000000', 'loss_value' => '0.000000',
                ]);
            }
            $this->event($order, 'created', ['planned_output_quantity' => $plannedOutputQuantity]);

            return $order->refresh()->load('materials');
        }, 3);
    }

    public function sendMaterials(int $companyId, int $orderId): SubcontractOrder
    {
        return DB::transaction(function () use ($companyId, $orderId): SubcontractOrder {
            $order = $this->lockOrder($companyId, $orderId);
            if ($order->sent_at !== null) {
                return $order->refresh()->load('materials');
            }
            if ($order->status !== 'draft') {
                throw new DomainException('Fason malzeme çıkışı yalnız taslak siparişte yapılabilir.');
            }
            $materials = $this->lockMaterials($companyId, $orderId);
            $sentValue = '0.000000';
            foreach ($materials as $material) {
                $posting = $this->stockPoster->post(new PostStockMovementData(
                    sourceEffect: new SourceEffectIdentity($companyId, 'subcontract.material', (string) $this->id($material), 'subcontract.material_send'),
                    productId: (int) $material->product_id, warehouseId: (int) $order->warehouse_id, locationId: (int) $order->location_id,
                    movementType: StockMovementType::SubcontractSendOut, quantity: (string) $material->planned_quantity,
                    note: 'Fason '.$order->order_no.' malzeme gönderimi',
                ));
                $value = $this->absolute((string) $posting->movement->value_delta);
                $material->update(['sent_quantity' => (string) $material->planned_quantity, 'sent_value' => $value, 'send_stock_movement_id' => $this->id($posting->movement)]);
                $sentValue = $this->add($sentValue, $value);
            }
            $order->update(['status' => 'in_progress', 'sent_value' => $sentValue, 'sent_at' => $this->clock->now()]);
            $this->event($order, 'materials_sent', ['sent_value' => $sentValue]);

            return $order->refresh()->load('materials');
        }, 3);
    }

    public function recordLoss(int $companyId, int $orderId, string $operationKey, int $productId, string $quantity, string $lossType, ?string $note = null): SubcontractLoss
    {
        $operationKey = $this->requiredText($operationKey, 64, 'Fason fire/eksik işlem anahtarı');
        $quantity = $this->positiveDecimal($quantity, 'Fason fire/eksik miktarı');
        $lossType = trim($lossType);
        $note = $this->nullableText($note, 240);
        if (! in_array($lossType, ['fire', 'missing'], true)) {
            throw new InvalidArgumentException('Fason kayıp türü fire veya missing olmalıdır.');
        }

        return DB::transaction(function () use ($companyId, $orderId, $operationKey, $productId, $quantity, $lossType, $note): SubcontractLoss {
            $order = $this->lockOrder($companyId, $orderId);
            if ($order->status !== 'in_progress') {
                throw new DomainException('Fason fire/eksik yalnız devam eden siparişte kaydedilebilir.');
            }
            $existing = SubcontractLoss::query()->where('company_id', $companyId)->where('operation_key', $operationKey)->first();
            if ($existing instanceof SubcontractLoss) {
                if ((int) $existing->subcontract_order_id !== $orderId || (int) $existing->product_id !== $productId || (string) $existing->quantity !== $quantity || $existing->loss_type !== $lossType || $existing->note !== $note) {
                    throw new DomainException('Aynı fason fire/eksik anahtarı farklı payload ile kullanılamaz.');
                }

                return $existing;
            }

            $material = SubcontractOrderMaterial::query()->where('company_id', $companyId)->where('subcontract_order_id', $orderId)->where('product_id', $productId)->lockForUpdate()->firstOrFail();
            $remaining = $this->remaining($material);
            if ($this->compare($quantity, $remaining) > 0) {
                throw new DomainException('Fason fire/eksik miktarı kalan subcontract custody miktarını aşamaz.');
            }
            $value = $this->proportionalValue($quantity, (string) $material->sent_value, (string) $material->sent_quantity);
            $loss = SubcontractLoss::query()->create([
                'company_id' => $companyId, 'subcontract_order_id' => $orderId, 'product_id' => $productId, 'operation_key' => $operationKey,
                'loss_type' => $lossType, 'quantity' => $quantity, 'carrying_value' => $value, 'note' => $note, 'occurred_at' => $this->clock->now(),
            ]);
            $material->update(['loss_quantity' => $this->add((string) $material->loss_quantity, $quantity), 'loss_value' => $this->add((string) $material->loss_value, $value)]);
            $order->update(['loss_value' => $this->add((string) $order->loss_value, $value)]);
            $this->event($order, 'loss_recorded', ['product_id' => $productId, 'quantity' => $quantity, 'carrying_value' => $value, 'loss_type' => $lossType]);

            return $loss;
        }, 3);
    }

    /** @param list<array{product_id:int,quantity:string}> $consumption */
    public function receiveOutput(int $companyId, int $orderId, string $operationKey, string $outputQuantity, array $consumption): SubcontractReceipt
    {
        $operationKey = $this->requiredText($operationKey, 64, 'Fason mamul kabul işlem anahtarı');
        $outputQuantity = $this->positiveDecimal($outputQuantity, 'Fason mamul kabul miktarı');
        if ($consumption === []) {
            throw new InvalidArgumentException('Fason mamul kabulünde tüketilen custody malzemeleri zorunludur.');
        }
        $normalized = [];
        foreach ($consumption as $row) {
            $productId = (int) $row['product_id'];
            if ($productId < 1 || isset($normalized[$productId])) {
                throw new InvalidArgumentException('Fason tüketim satırları benzersiz ve geçerli olmalıdır.');
            }
            $normalized[$productId] = $this->positiveDecimal((string) $row['quantity'], 'Fason tüketim miktarı');
        }
        ksort($normalized);
        $payload = array_map(static fn (int $productId, string $quantity): array => ['product_id' => $productId, 'quantity' => $quantity], array_keys($normalized), array_values($normalized));

        return DB::transaction(function () use ($companyId, $orderId, $operationKey, $outputQuantity, $normalized, $payload): SubcontractReceipt {
            $order = $this->lockOrder($companyId, $orderId);
            if ($order->status !== 'in_progress' || $order->sent_at === null) {
                throw new DomainException('Fason mamul kabulü için malzeme gönderimi tamamlanmış olmalıdır.');
            }
            $existing = SubcontractReceipt::query()->where('company_id', $companyId)->where('operation_key', $operationKey)->first();
            if ($existing instanceof SubcontractReceipt) {
                if ((int) $existing->subcontract_order_id !== $orderId || (string) $existing->output_quantity !== $outputQuantity || $existing->consumption_payload !== $payload) {
                    throw new DomainException('Aynı fason mamul kabul anahtarı farklı payload ile kullanılamaz.');
                }

                return $existing;
            }

            $materials = SubcontractOrderMaterial::query()->where('company_id', $companyId)->where('subcontract_order_id', $orderId)->whereIn('product_id', array_keys($normalized))->lockForUpdate()->get()->keyBy('product_id');
            if ($materials->count() !== count($normalized)) {
                throw new DomainException('Fason tüketim satırlarından biri sipariş custody kapsamı dışında.');
            }
            $carryingValue = '0.000000';
            foreach ($normalized as $productId => $quantity) {
                $material = $materials->get($productId);
                if (! $material instanceof SubcontractOrderMaterial) {
                    throw new LogicException('Fason custody malzemesi bulunamadı.');
                }
                if ($this->compare($quantity, $this->remaining($material)) > 0) {
                    throw new DomainException('Fason tüketim miktarı kalan subcontract custody miktarını aşamaz.');
                }
                $value = $this->proportionalValue($quantity, (string) $material->sent_value, (string) $material->sent_quantity);
                $material->update(['consumed_quantity' => $this->add((string) $material->consumed_quantity, $quantity), 'consumed_value' => $this->add((string) $material->consumed_value, $value)]);
                $carryingValue = $this->add($carryingValue, $value);
            }
            if ($this->compare($carryingValue, '0.000000') <= 0) {
                throw new LogicException('Fason mamul kabul taşıma değeri sıfır olamaz.');
            }
            $unitCost = $this->divide($carryingValue, $outputQuantity);
            $receipt = SubcontractReceipt::query()->create([
                'company_id' => $companyId, 'subcontract_order_id' => $orderId, 'operation_key' => $operationKey,
                'output_quantity' => $outputQuantity, 'carrying_value' => $carryingValue, 'consumption_payload' => $payload, 'occurred_at' => $this->clock->now(),
            ]);
            $posting = $this->stockPoster->post(new PostStockMovementData(
                sourceEffect: new SourceEffectIdentity($companyId, 'subcontract.receipt', (string) $this->id($receipt), 'subcontract.finished_goods_receipt'),
                productId: (int) $order->output_product_id, warehouseId: (int) $order->warehouse_id, locationId: (int) $order->location_id,
                movementType: StockMovementType::SubcontractReceiptIn, quantity: $outputQuantity, unitCost: $unitCost, carryingValue: $carryingValue,
                note: 'Fason '.$order->order_no.' mamul kabulü',
            ));
            $receipt->update(['stock_movement_id' => $this->id($posting->movement)]);
            $order->update([
                'received_output_quantity' => $this->add((string) $order->received_output_quantity, $outputQuantity),
                'received_output_value' => $this->add((string) $order->received_output_value, $carryingValue),
                'received_at' => $order->received_at ?? $this->clock->now(),
            ]);
            $this->event($order, 'output_received', ['receipt_id' => $this->id($receipt), 'output_quantity' => $outputQuantity, 'carrying_value' => $carryingValue]);

            return $receipt->refresh();
        }, 3);
    }

    public function complete(int $companyId, int $orderId): SubcontractOrder
    {
        return DB::transaction(function () use ($companyId, $orderId): SubcontractOrder {
            $order = $this->lockOrder($companyId, $orderId);
            if ($order->status === 'completed') {
                return $order;
            }
            if ($order->status !== 'in_progress' || $order->received_at === null) {
                throw new DomainException('Fason sipariş en az bir mamul kabulü olmadan tamamlanamaz.');
            }
            foreach ($this->lockMaterials($companyId, $orderId) as $material) {
                if ($this->compare($this->remaining($material), '0.000000') !== 0) {
                    throw new DomainException('Fason sipariş tamamlanmadan önce tüm subcontract custody miktarı tüketilmeli veya fire/eksik olarak reconcile edilmelidir.');
                }
            }
            $order->update(['status' => 'completed', 'completed_at' => $this->clock->now()]);
            $this->event($order, 'completed', ['received_output_quantity' => (string) $order->received_output_quantity, 'received_output_value' => (string) $order->received_output_value]);

            return $order->refresh();
        }, 3);
    }

    private function lockOrder(int $companyId, int $orderId): SubcontractOrder
    {
        return SubcontractOrder::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($orderId);
    }

    /** @return Collection<int, SubcontractOrderMaterial> */
    private function lockMaterials(int $companyId, int $orderId): Collection
    {
        return SubcontractOrderMaterial::query()->where('company_id', $companyId)->where('subcontract_order_id', $orderId)->orderBy('id')->lockForUpdate()->get();
    }

    private function remaining(SubcontractOrderMaterial $material): string
    {
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) - CAST(? AS numeric) - CAST(? AS numeric) AS numeric(20,6))::text AS value', [(string) $material->sent_quantity, (string) $material->consumed_quantity, (string) $material->loss_quantity]);

        return $row === null ? throw new LogicException('Fason remaining hesaplanamadı.') : (string) $row->value;
    }

    private function proportionalValue(string $quantity, string $sentValue, string $sentQuantity): string
    {
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) * CAST(? AS numeric) / CAST(? AS numeric) AS numeric(20,6))::text AS value', [$quantity, $sentValue, $sentQuantity]);

        return $row === null ? throw new LogicException('Fason taşıma değeri hesaplanamadı.') : (string) $row->value;
    }

    private function add(string $left, string $right): string
    {
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) + CAST(? AS numeric) AS numeric(20,6))::text AS value', [$left, $right]);

        return $row === null ? throw new LogicException('Fason toplama hesaplanamadı.') : (string) $row->value;
    }

    private function divide(string $left, string $right): string
    {
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) / CAST(? AS numeric) AS numeric(20,6))::text AS value', [$left, $right]);

        return $row === null ? throw new LogicException('Fason birim maliyet hesaplanamadı.') : (string) $row->value;
    }

    private function absolute(string $value): string
    {
        $row = DB::selectOne('SELECT CAST(abs(CAST(? AS numeric)) AS numeric(20,6))::text AS value', [$value]);

        return $row === null ? throw new LogicException('Fason mutlak değer hesaplanamadı.') : (string) $row->value;
    }

    private function compare(string $left, string $right): int
    {
        $row = DB::selectOne('SELECT CASE WHEN CAST(? AS numeric) < CAST(? AS numeric) THEN -1 WHEN CAST(? AS numeric) > CAST(? AS numeric) THEN 1 ELSE 0 END AS value', [$left, $right, $left, $right]);

        return $row === null ? throw new LogicException('Fason numeric karşılaştırma hesaplanamadı.') : (int) $row->value;
    }

    private function positiveDecimal(string $value, string $label): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw new InvalidArgumentException($label.' pozitif ve en fazla 6 ondalıklı olmalıdır.');
        }
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) > 0 AS valid', [$value, $value]);
        if ($row === null || $row->valid !== true) {
            throw new InvalidArgumentException($label.' sıfırdan büyük olmalıdır.');
        }

        return (string) $row->value;
    }

    private function requiredText(string $value, int $max, string $label): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException($label.' zorunludur ve en fazla '.$max.' karakter olabilir.');
        }

        return $value;
    }

    private function nullableText(?string $value, int $max): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Not en fazla '.$max.' karakter olabilir.');
        }

        return $value;
    }

    /** @param array<string, mixed>|null $payload */
    private function event(SubcontractOrder $order, string $type, ?array $payload = null): void
    {
        SubcontractEvent::query()->create(['company_id' => (int) $order->company_id, 'subcontract_order_id' => $this->id($order), 'event_type' => $type, 'payload' => $payload, 'occurred_at' => $this->clock->now(), 'created_at' => $this->clock->now()]);
    }

    private function id(Model $model): int
    {
        $id = $model->getKey();

        return is_int($id) ? $id : throw new LogicException('Fason kaydı persisted integer id döndürmedi.');
    }
}
