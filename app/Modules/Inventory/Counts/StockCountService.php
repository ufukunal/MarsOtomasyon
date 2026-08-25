<?php

namespace App\Modules\Inventory\Counts;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class StockCountService
{
    public function __construct(
        private StockMovementPoster $stockMovementPoster,
        private Clock $clock,
    ) {}

    public function start(int $companyId, int $locationId, string $operationKey): StockCount
    {
        $this->assertInsideTransaction();
        $operationKey = trim($operationKey);
        if ($operationKey === '' || mb_strlen($operationKey) > 64) {
            throw ValidationException::withMessages([
                'operation_key' => 'Sayım işlem anahtarı boş olamaz ve 64 karakteri aşamaz.',
            ]);
        }

        $location = WarehouseLocation::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereHas('warehouse', fn ($query) => $query->where('is_active', true))
            ->findOrFail($locationId);

        $existing = StockCount::query()
            ->where('company_id', $companyId)
            ->where('operation_key', $operationKey)
            ->lockForUpdate()
            ->first();
        if ($existing instanceof StockCount) {
            if ((int) $existing->location_id !== $locationId) {
                throw ValidationException::withMessages([
                    'operation_key' => 'Aynı sayım işlem anahtarı farklı lokasyon için kullanılamaz.',
                ]);
            }

            return $existing;
        }

        $count = StockCount::query()->create([
            'company_id' => $companyId,
            'warehouse_id' => $location->warehouse_id,
            'location_id' => $location->getKey(),
            'operation_key' => $operationKey,
            'status' => 'draft',
            'started_at' => $this->clock->now(),
        ]);

        $balances = StockBalance::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $location->warehouse_id)
            ->where('location_id', $location->getKey())
            ->where('quantity', '>', 0)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get();

        foreach ($balances as $balance) {
            StockCountLine::query()->create([
                'company_id' => $companyId,
                'stock_count_id' => $count->getKey(),
                'product_id' => $balance->product_id,
                'expected_quantity' => $balance->quantity,
                'expected_unit_cost' => $balance->average_unit_cost,
                'expected_value' => $balance->inventory_value,
                'counted_quantity' => '0.000000',
            ]);
        }

        return $count;
    }

    public function setCounted(
        int $companyId,
        int $countId,
        int $productId,
        string $countedQuantity,
        ?string $valuationUnitCost = null,
    ): StockCountLine {
        $this->assertInsideTransaction();
        $countedQuantity = $this->nonNegativeDecimal($countedQuantity, 'counted_quantity');
        $valuationUnitCost = $valuationUnitCost === null || trim($valuationUnitCost) === ''
            ? null
            : $this->positiveDecimal($valuationUnitCost, 'valuation_unit_cost');

        $count = $this->draftCount($companyId, $countId);
        Product::query()->where('company_id', $companyId)->findOrFail($productId);

        $line = StockCountLine::query()
            ->where('company_id', $companyId)
            ->where('stock_count_id', $count->getKey())
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (! $line instanceof StockCountLine) {
            $line = StockCountLine::query()->create([
                'company_id' => $companyId,
                'stock_count_id' => $count->getKey(),
                'product_id' => $productId,
                'expected_quantity' => '0.000000',
                'expected_unit_cost' => '0.000000',
                'expected_value' => '0.000000',
                'counted_quantity' => $countedQuantity,
                'valuation_unit_cost' => $valuationUnitCost,
            ]);

            return $line->refresh();
        }

        $line->counted_quantity = $countedQuantity;
        $line->valuation_unit_cost = $valuationUnitCost;
        $line->save();

        return $line->refresh();
    }

    public function scanBarcode(
        int $companyId,
        int $countId,
        string $barcode,
        string $quantity = '1',
    ): StockCountLine {
        $this->assertInsideTransaction();
        $barcode = trim($barcode);
        if ($barcode === '') {
            throw ValidationException::withMessages(['barcode' => 'Barkod boş olamaz.']);
        }
        $quantity = $this->positiveDecimal($quantity, 'quantity');
        $count = $this->draftCount($companyId, $countId);

        $barcodeModel = Barcode::query()
            ->where('company_id', $companyId)
            ->where('barcode', $barcode)
            ->first();
        if (! $barcodeModel instanceof Barcode) {
            throw ValidationException::withMessages(['barcode' => 'Barkod aktif şirket ürünlerinde bulunamadı.']);
        }

        $line = StockCountLine::query()
            ->where('company_id', $companyId)
            ->where('stock_count_id', $count->getKey())
            ->where('product_id', $barcodeModel->product_id)
            ->lockForUpdate()
            ->first();

        if (! $line instanceof StockCountLine) {
            $line = StockCountLine::query()->create([
                'company_id' => $companyId,
                'stock_count_id' => $count->getKey(),
                'product_id' => $barcodeModel->product_id,
                'expected_quantity' => '0.000000',
                'expected_unit_cost' => '0.000000',
                'expected_value' => '0.000000',
                'counted_quantity' => $quantity,
            ]);

            return $line->refresh();
        }

        $line->counted_quantity = $this->add((string) $line->counted_quantity, $quantity);
        $line->save();

        return $line->refresh();
    }

    public function post(int $companyId, int $countId): StockCount
    {
        $this->assertInsideTransaction();
        $count = StockCount::query()
            ->where('company_id', $companyId)
            ->whereKey($countId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($count->status === 'posted') {
            return $count;
        }

        $lines = StockCountLine::query()
            ->where('company_id', $companyId)
            ->where('stock_count_id', $count->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $balances = StockBalance::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $count->warehouse_id)
            ->where('location_id', $count->location_id)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get();

        $this->assertFreshSnapshot($lines, $balances);

        foreach ($lines as $line) {
            $variance = (string) $line->variance_quantity;
            if ($this->equals($variance, '0')) {
                continue;
            }

            $movementType = $this->greaterThan($variance, '0')
                ? StockMovementType::AdjustmentIn
                : StockMovementType::AdjustmentOut;
            $quantity = $this->absolute($variance);
            $unitCost = null;
            if ($movementType === StockMovementType::AdjustmentIn) {
                $unitCost = $line->valuation_unit_cost !== null
                    ? (string) $line->valuation_unit_cost
                    : (string) $line->expected_unit_cost;
                if (! $this->greaterThan($unitCost, '0')) {
                    throw ValidationException::withMessages([
                        'valuation_unit_cost' => 'Pozitif sayım farkı için değerleme birim maliyeti zorunludur.',
                    ]);
                }
            }

            $posting = $this->stockMovementPoster->post(new PostStockMovementData(
                sourceEffect: new SourceEffectIdentity(
                    companyId: $companyId,
                    sourceType: 'inventory.stock_count',
                    sourceId: 'count-'.$count->getKey().'-line-'.$line->getKey(),
                    effectType: 'inventory.count_adjustment',
                ),
                productId: (int) $line->product_id,
                warehouseId: (int) $count->warehouse_id,
                locationId: (int) $count->location_id,
                movementType: $movementType,
                quantity: $quantity,
                unitCost: $unitCost,
                note: 'Stock count #'.$count->getKey().' variance',
            ));

            $line->adjustment_movement_id = $posting->movement->getKey();
            $line->save();
        }

        $count->status = 'posted';
        $count->posted_at = $this->clock->now();
        $count->save();

        return $count->refresh();
    }

    private function draftCount(int $companyId, int $countId): StockCount
    {
        $count = StockCount::query()
            ->where('company_id', $companyId)
            ->whereKey($countId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($count->status !== 'draft') {
            throw ValidationException::withMessages(['stock_count' => 'Tamamlanmış sayım değiştirilemez.']);
        }

        return $count;
    }

    /**
     * @param  Collection<int, StockCountLine>  $lines
     * @param  Collection<int, StockBalance>  $balances
     */
    private function assertFreshSnapshot(Collection $lines, Collection $balances): void
    {
        $lineByProduct = $lines->keyBy(fn (StockCountLine $line): int => (int) $line->product_id);
        $balanceByProduct = $balances->keyBy(fn (StockBalance $balance): int => (int) $balance->product_id);

        foreach ($lines as $line) {
            $balance = $balanceByProduct->get((int) $line->product_id);
            $actualQuantity = $balance instanceof StockBalance ? (string) $balance->quantity : '0.000000';
            $actualValue = $balance instanceof StockBalance ? (string) $balance->inventory_value : '0.000000';

            if (! $this->equals($actualQuantity, (string) $line->expected_quantity)
                || ! $this->equals($actualValue, (string) $line->expected_value)) {
                throw ValidationException::withMessages([
                    'stock_count' => 'Sayım snapshot sonrasında stok değişti. Sayım yeniden başlatılmalıdır.',
                ]);
            }
        }

        foreach ($balances as $balance) {
            if ($this->greaterThan((string) $balance->quantity, '0')
                && ! $lineByProduct->has((int) $balance->product_id)) {
                throw ValidationException::withMessages([
                    'stock_count' => 'Sayım snapshot sonrasında lokasyona yeni stok girdi. Sayım yeniden başlatılmalıdır.',
                ]);
            }
        }
    }

    private function nonNegativeDecimal(string $value, string $field): string
    {
        return $this->decimal($value, $field, false);
    }

    private function positiveDecimal(string $value, string $field): string
    {
        return $this->decimal($value, $field, true);
    }

    private function decimal(string $value, string $field, bool $positive): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages([
                $field => 'Miktar en fazla 6 ondalıklı pozitif sayısal değer olmalıdır.',
            ]);
        }
        $integerPart = explode('.', $value, 2)[0];
        if (strlen(ltrim($integerPart, '0')) > 14) {
            throw ValidationException::withMessages([$field => 'Miktar desteklenen aralığı aşıyor.']);
        }

        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) > 0 AS positive',
            [$value, $value],
        );
        if ($row === null || ($positive && $row->positive !== true)) {
            throw ValidationException::withMessages([$field => 'Miktar sıfırdan büyük olmalıdır.']);
        }

        return (string) $row->value;
    }

    private function add(string $left, string $right): string
    {
        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) + CAST(? AS numeric) AS numeric(20,6))::text AS value',
            [$left, $right],
        );

        return $row === null
            ? throw new LogicException('Stock count numeric addition did not return a value.')
            : (string) $row->value;
    }

    private function absolute(string $value): string
    {
        $row = DB::selectOne('SELECT abs(CAST(? AS numeric))::text AS value', [$value]);

        return $row === null
            ? throw new LogicException('Stock count numeric absolute did not return a value.')
            : (string) $row->value;
    }

    private function equals(string $left, string $right): bool
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) = CAST(? AS numeric) AS valid', [$left, $right]);

        return $row !== null && $row->valid === true;
    }

    private function greaterThan(string $left, string $right): bool
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) > CAST(? AS numeric) AS valid', [$left, $right]);

        return $row !== null && $row->valid === true;
    }

    private function assertInsideTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Stok sayımı aynı business transaction içinde çalışmalıdır.');
        }
    }
}
