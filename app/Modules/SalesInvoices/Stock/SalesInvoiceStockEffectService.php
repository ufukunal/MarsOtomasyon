<?php

namespace App\Modules\SalesInvoices\Stock;

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Dispatches\Models\DispatchLine;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Ledger\StockMovementReverser;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Reservations\StockReservationService;
use App\Modules\SalesInvoices\Enums\SalesInvoiceMode;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesInvoices\Models\SalesInvoiceLine;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use App\Modules\SalesOrders\Models\SalesOrderLineProgress;
use App\Modules\SalesOrders\Models\SalesOrderReservationGeneration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class SalesInvoiceStockEffectService
{
    private const SOURCE_TYPE = 'sales_invoice_line';

    private const STOCK_EFFECT = 'stock.out';

    public function __construct(
        private StockMovementPoster $stock,
        private StockMovementReverser $reverser,
        private StockReservationService $reservations,
    ) {}

    /** @return list<StockMovement> */
    public function post(SalesInvoice $invoice): array
    {
        $this->assertInsideTransaction();

        $locked = $this->lockedInvoice($invoice);
        $lines = $locked->lines()->lockForUpdate()->get();
        if ($lines->isEmpty()) {
            throw new LogicException('Stok effecti üretilecek satış faturası satırsız olamaz.');
        }

        if ($locked->modeEnum() === SalesInvoiceMode::DispatchLinked) {
            foreach ($lines as $line) {
                $this->assertDispatchStockCoverage($line);
            }

            return [];
        }

        /** @var list<StockMovement> $movements */
        $movements = [];
        foreach ($lines as $line) {
            $movements[] = $locked->modeEnum() === SalesInvoiceMode::OrderLinked
                ? $this->postOrderLinkedLine($line)
                : $this->postDirectLine($line);
        }

        return $movements;
    }

    /** @return list<StockMovement> */
    public function reverse(SalesInvoice $invoice): array
    {
        $this->assertInsideTransaction();

        $locked = $this->lockedInvoice($invoice);
        $lines = $locked->lines()->lockForUpdate()->get();
        if ($lines->isEmpty()) {
            throw new LogicException('Stok reversal üretilecek satış faturası satırsız olamaz.');
        }

        if ($locked->modeEnum() === SalesInvoiceMode::DispatchLinked) {
            foreach ($lines as $line) {
                $this->assertDispatchStockCoverage($line);
            }

            return [];
        }

        /** @var list<StockMovement> $reversals */
        $reversals = [];
        foreach ($lines as $line) {
            $original = StockMovement::query()
                ->where('company_id', $line->company_id)
                ->where('source_type', self::SOURCE_TYPE)
                ->where('source_id', (string) $line->getKey())
                ->where('effect_type', self::STOCK_EFFECT)
                ->where('movement_type', StockMovementType::InvoiceOut->value)
                ->first();

            if (! $original instanceof StockMovement) {
                throw new LogicException('Satış faturası stok çıkış effecti bulunamadı.');
            }

            $reversals[] = $this->reverser->reverse(
                (int) $original->getKey(),
                $this->identity($line, 'stock.out.reverse'),
                'Satış faturası iptal stok iadesi #'.(int) $line->sales_invoice_id,
            )->movement;
        }

        return $reversals;
    }

    public function reconcileOrderReservationAfterReversal(SalesInvoiceLine $invoiceLine): void
    {
        $this->assertInsideTransaction();

        if ($invoiceLine->source_sales_order_id === null || $invoiceLine->source_sales_order_line_id === null) {
            throw new LogicException('Sipariş bağlı fatura satırı order lineage taşımıyor.');
        }

        $orderLine = SalesOrderLine::query()
            ->where('company_id', $invoiceLine->company_id)
            ->where('sales_order_id', $invoiceLine->source_sales_order_id)
            ->whereKey($invoiceLine->source_sales_order_line_id)
            ->lockForUpdate()
            ->first();

        if (! $orderLine instanceof SalesOrderLine) {
            throw new LogicException('Satış faturası kaynak sipariş satırı bulunamadı.');
        }

        if ($orderLine->warehouse_id === null && $orderLine->location_id === null) {
            return;
        }

        $logicalKey = $orderLine->logical_line_key;
        if (! is_string($logicalKey)
            || $logicalKey === ''
            || $orderLine->warehouse_id === null
            || $orderLine->location_id === null) {
            throw new LogicException('Allocation bağlı sipariş satırının reservation kimliği eksik.');
        }

        $projection = SalesOrderLineProgress::query()
            ->where('company_id', $invoiceLine->company_id)
            ->where('sales_order_id', $invoiceLine->source_sales_order_id)
            ->where('sales_order_line_id', $invoiceLine->source_sales_order_line_id)
            ->first();

        if (! $projection instanceof SalesOrderLineProgress) {
            throw new LogicException('Sipariş sevk kalan miktar projection satırı bulunamadı.');
        }

        $targetQuantity = $projection->getAttribute('dispatch_remaining_quantity');
        if (! is_string($targetQuantity)) {
            throw new LogicException('Sipariş sevk kalan miktar projection değeri geçersiz.');
        }

        $active = SalesOrderReservationGeneration::query()
            ->where('company_id', $orderLine->company_id)
            ->where('sales_order_id', $orderLine->sales_order_id)
            ->where('logical_line_key', $logicalKey)
            ->whereNull('released_at')
            ->lockForUpdate()
            ->first();

        if ($targetQuantity === '0.000000') {
            if ($active instanceof SalesOrderReservationGeneration) {
                $this->releaseGeneration($invoiceLine, $active);
            }

            return;
        }

        if ($active instanceof SalesOrderReservationGeneration
            && $this->generationMatches($active, $orderLine, $targetQuantity)) {
            return;
        }

        if ($active instanceof SalesOrderReservationGeneration) {
            $this->releaseGeneration($invoiceLine, $active);
        }

        $result = $this->reservations->reserve(
            $this->identity($invoiceLine, 'reservation.reconcile.reserve'),
            (int) $orderLine->product_id,
            (int) $orderLine->warehouse_id,
            (int) $orderLine->location_id,
            $targetQuantity,
        );

        SalesOrderReservationGeneration::query()->create([
            'company_id' => (int) $orderLine->company_id,
            'sales_order_id' => (int) $orderLine->sales_order_id,
            'logical_line_key' => $logicalKey,
            'generation' => $this->nextGeneration($orderLine, $logicalKey),
            'product_id' => (int) $orderLine->product_id,
            'warehouse_id' => (int) $orderLine->warehouse_id,
            'location_id' => (int) $orderLine->location_id,
            'quantity' => $targetQuantity,
            'stock_reservation_id' => (int) $result->reservation->getKey(),
        ]);
    }

    private function postDirectLine(SalesInvoiceLine $line): StockMovement
    {
        if ($line->source_sales_order_line_id !== null || $line->source_dispatch_line_id !== null) {
            throw new LogicException('Doğrudan fatura satırı linked source taşımamalıdır.');
        }

        return $this->postMovement($line);
    }

    private function postOrderLinkedLine(SalesInvoiceLine $line): StockMovement
    {
        $existing = $this->existingMovement($line);
        if ($existing instanceof StockMovement) {
            $this->assertReplayMatches($existing, $line);

            return $existing;
        }

        if ($line->source_sales_order_id === null
            || $line->source_sales_order_line_id === null
            || $line->source_dispatch_line_id !== null) {
            throw new LogicException('Sipariş bağlı fatura satırı order-only lineage taşımalıdır.');
        }

        $orderLine = SalesOrderLine::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->source_sales_order_id)
            ->whereKey($line->source_sales_order_line_id)
            ->lockForUpdate()
            ->first();

        if (! $orderLine instanceof SalesOrderLine) {
            throw ValidationException::withMessages([
                'sales_invoice' => 'Fatura stok çıkışı için kaynak sipariş satırı bulunamadı.',
            ]);
        }

        $this->assertOrderLineScope($orderLine, $line);
        $generation = $this->activeReservationGeneration($orderLine);
        if ($generation instanceof SalesOrderReservationGeneration) {
            $this->assertReservationScope($generation, $line);
            $comparison = $this->compare((string) $line->quantity, (string) $generation->quantity);
            if ($comparison > 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Fatura stok çıkışı aktif sipariş rezervasyonunu aşamaz.',
                ]);
            }

            if ($comparison === 0) {
                $result = $this->reservations->consume(
                    $this->identity($line, 'reservation.consume'),
                    (int) $generation->stock_reservation_id,
                );
                $generation->released_at = $result->reservation->consumed_at;
                $generation->save();

                return $this->postMovement($line);
            }

            $released = $this->reservations->release(
                $this->identity($line, 'reservation.release'),
                (int) $generation->stock_reservation_id,
            );
            $generation->released_at = $released->reservation->released_at;
            $generation->save();

            $movement = $this->postMovement($line);
            $remaining = $this->subtract((string) $generation->quantity, (string) $line->quantity);
            $replacement = $this->reservations->reserve(
                $this->identity($line, 'reservation.remainder'),
                (int) $generation->product_id,
                (int) $generation->warehouse_id,
                (int) $generation->location_id,
                $remaining,
            );

            SalesOrderReservationGeneration::query()->create([
                'company_id' => (int) $generation->company_id,
                'sales_order_id' => (int) $generation->sales_order_id,
                'logical_line_key' => (string) $generation->logical_line_key,
                'generation' => $this->nextGeneration($orderLine, (string) $generation->logical_line_key),
                'product_id' => (int) $generation->product_id,
                'warehouse_id' => (int) $generation->warehouse_id,
                'location_id' => (int) $generation->location_id,
                'quantity' => $remaining,
                'stock_reservation_id' => (int) $replacement->reservation->getKey(),
            ]);

            return $movement;
        }

        if ($orderLine->warehouse_id !== null || $orderLine->location_id !== null) {
            throw ValidationException::withMessages([
                'sales_invoice' => 'Allocation bağlı sipariş satırı için aktif stok rezervasyon generation bulunamadı.',
            ]);
        }

        return $this->postMovement($line);
    }

    private function postMovement(SalesInvoiceLine $line): StockMovement
    {
        $existing = $this->existingMovement($line);
        if ($existing instanceof StockMovement) {
            $this->assertReplayMatches($existing, $line);

            return $existing;
        }

        return $this->stock->post(new PostStockMovementData(
            sourceEffect: $this->identity($line, self::STOCK_EFFECT),
            productId: (int) $line->product_id,
            warehouseId: (int) $line->warehouse_id,
            locationId: (int) $line->location_id,
            movementType: StockMovementType::InvoiceOut,
            quantity: (string) $line->quantity,
            note: 'Satış faturası stok çıkışı #'.(int) $line->sales_invoice_id,
        ))->movement;
    }

    private function existingMovement(SalesInvoiceLine $line): ?StockMovement
    {
        return StockMovement::query()
            ->where('company_id', $line->company_id)
            ->where('source_type', self::SOURCE_TYPE)
            ->where('source_id', (string) $line->getKey())
            ->where('effect_type', self::STOCK_EFFECT)
            ->first();
    }

    private function assertDispatchStockCoverage(SalesInvoiceLine $invoiceLine): void
    {
        if ($invoiceLine->source_dispatch_id === null || $invoiceLine->source_dispatch_line_id === null) {
            throw new LogicException('İrsaliye bağlı fatura satırı dispatch lineage taşımıyor.');
        }

        $dispatchLine = DispatchLine::query()
            ->where('company_id', $invoiceLine->company_id)
            ->where('dispatch_id', $invoiceLine->source_dispatch_id)
            ->whereKey($invoiceLine->source_dispatch_line_id)
            ->sharedLock()
            ->first();

        if (! $dispatchLine instanceof DispatchLine) {
            throw new LogicException('İrsaliye bağlı fatura kaynak satırı bulunamadı.');
        }

        if ((int) $dispatchLine->product_id !== (int) $invoiceLine->product_id
            || (int) $dispatchLine->warehouse_id !== (int) $invoiceLine->warehouse_id
            || (int) $dispatchLine->location_id !== (int) $invoiceLine->location_id
            || $this->compare((string) $invoiceLine->quantity, (string) $dispatchLine->quantity) > 0) {
            throw new LogicException('İrsaliye bağlı fatura satırı source dispatch stok scope/miktarı ile uyuşmuyor.');
        }

        $movement = StockMovement::query()
            ->where('company_id', $invoiceLine->company_id)
            ->where('source_type', 'dispatch_line')
            ->where('source_id', (string) $dispatchLine->getKey())
            ->where('effect_type', 'stock.out')
            ->where('movement_type', StockMovementType::DispatchOut->value)
            ->first();

        if (! $movement instanceof StockMovement
            || (int) $movement->product_id !== (int) $dispatchLine->product_id
            || (int) $movement->warehouse_id !== (int) $dispatchLine->warehouse_id
            || (int) $movement->location_id !== (int) $dispatchLine->location_id
            || $this->compare($this->absolute((string) $movement->quantity_delta), (string) $dispatchLine->quantity) !== 0) {
            throw new LogicException('İrsaliye bağlı faturanın source dispatch stok çıkışı eksik veya tutarsız.');
        }
    }

    private function assertOrderLineScope(SalesOrderLine $orderLine, SalesInvoiceLine $invoiceLine): void
    {
        if ((int) $orderLine->product_id !== (int) $invoiceLine->product_id) {
            throw new LogicException('Fatura ve kaynak sipariş ürün lineage değeri uyuşmuyor.');
        }

        $orderWarehouse = $orderLine->warehouse_id === null ? null : (int) $orderLine->warehouse_id;
        $orderLocation = $orderLine->location_id === null ? null : (int) $orderLine->location_id;
        if (($orderWarehouse === null) !== ($orderLocation === null)) {
            throw new LogicException('Kaynak sipariş allocation scope değeri tutarsız.');
        }

        if ($orderWarehouse !== null
            && ($orderWarehouse !== (int) $invoiceLine->warehouse_id
                || $orderLocation !== (int) $invoiceLine->location_id)) {
            throw new LogicException('Fatura stok allocation scope kaynak sipariş allocation ile uyuşmuyor.');
        }
    }

    private function activeReservationGeneration(SalesOrderLine $line): ?SalesOrderReservationGeneration
    {
        $logicalKey = $line->logical_line_key;
        if (! is_string($logicalKey) || $logicalKey === '') {
            return null;
        }

        return SalesOrderReservationGeneration::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->sales_order_id)
            ->where('logical_line_key', $logicalKey)
            ->whereNull('released_at')
            ->lockForUpdate()
            ->first();
    }

    private function assertReservationScope(SalesOrderReservationGeneration $generation, SalesInvoiceLine $line): void
    {
        if ((int) $generation->product_id !== (int) $line->product_id
            || (int) $generation->warehouse_id !== (int) $line->warehouse_id
            || (int) $generation->location_id !== (int) $line->location_id) {
            throw ValidationException::withMessages([
                'sales_invoice' => 'Aktif sipariş rezervasyonu fatura stok allocation scope ile uyuşmuyor.',
            ]);
        }
    }

    private function releaseGeneration(SalesInvoiceLine $line, SalesOrderReservationGeneration $generation): void
    {
        $result = $this->reservations->release(
            $this->identity($line, 'reservation.reconcile.release'),
            (int) $generation->stock_reservation_id,
        );
        $generation->released_at = $result->reservation->released_at;
        $generation->save();
    }

    private function generationMatches(
        SalesOrderReservationGeneration $generation,
        SalesOrderLine $line,
        string $quantity,
    ): bool {
        return (int) $generation->product_id === (int) $line->product_id
            && (int) $generation->warehouse_id === (int) $line->warehouse_id
            && (int) $generation->location_id === (int) $line->location_id
            && (string) $generation->quantity === $quantity;
    }

    private function nextGeneration(SalesOrderLine $line, string $logicalKey): int
    {
        return ((int) SalesOrderReservationGeneration::query()
            ->where('company_id', $line->company_id)
            ->where('sales_order_id', $line->sales_order_id)
            ->where('logical_line_key', $logicalKey)
            ->max('generation')) + 1;
    }

    private function assertReplayMatches(StockMovement $movement, SalesInvoiceLine $line): void
    {
        if ((string) $movement->getRawOriginal('movement_type') !== StockMovementType::InvoiceOut->value
            || (int) $movement->product_id !== (int) $line->product_id
            || (int) $movement->warehouse_id !== (int) $line->warehouse_id
            || (int) $movement->location_id !== (int) $line->location_id
            || $this->compare($this->absolute((string) $movement->quantity_delta), (string) $line->quantity) !== 0) {
            throw new LogicException('Persisted sales invoice stock effect source invoice line ile uyuşmuyor.');
        }
    }

    private function lockedInvoice(SalesInvoice $invoice): SalesInvoice
    {
        $locked = SalesInvoice::query()
            ->where('company_id', $invoice->company_id)
            ->whereKey($invoice->getKey())
            ->lockForUpdate()
            ->first();

        return $locked instanceof SalesInvoice
            ? $locked
            : throw new LogicException('Stok effecti için satış faturası bulunamadı.');
    }

    private function identity(SalesInvoiceLine $line, string $effectType): SourceEffectIdentity
    {
        return new SourceEffectIdentity(
            (int) $line->company_id,
            self::SOURCE_TYPE,
            (string) $line->getKey(),
            $effectType,
        );
    }

    private function compare(string $left, string $right): int
    {
        $row = DB::selectOne(
            'SELECT CASE WHEN CAST(? AS numeric) < CAST(? AS numeric) THEN -1 WHEN CAST(? AS numeric) > CAST(? AS numeric) THEN 1 ELSE 0 END AS value',
            [$left, $right, $left, $right],
        );

        return $row === null
            ? throw new LogicException('Numeric comparison did not return a value.')
            : (int) $row->value;
    }

    private function subtract(string $left, string $right): string
    {
        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) - CAST(? AS numeric) AS numeric(20,6))::text AS value',
            [$left, $right],
        );

        return $row === null
            ? throw new LogicException('Numeric subtraction did not return a value.')
            : (string) $row->value;
    }

    private function absolute(string $value): string
    {
        $row = DB::selectOne('SELECT abs(CAST(? AS numeric))::text AS value', [$value]);

        return $row === null
            ? throw new LogicException('Numeric absolute did not return a value.')
            : (string) $row->value;
    }

    private function assertInsideTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Satış faturası stok effecti aynı business transaction içinde çalışmalıdır.');
        }
    }
}
