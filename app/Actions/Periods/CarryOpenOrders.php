<?php

namespace App\Actions\Periods;

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Documents\SourceLineAvailability;
use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Purchases\PurchaseLineAvailability;
use App\Actions\Purchases\SavePurchaseDocumentDraft;
use App\Actions\Stock\AdjustReservedBalance;
use App\Enums\DocumentType;
use App\Models\Period;
use App\Models\Period\Document;
use App\Models\Period\StockReservation;
use App\Support\Audit\AuditContext;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CarryOpenOrders
{
    public function __construct(
        private readonly SourceLineAvailability $salesAvailability,
        private readonly PurchaseLineAvailability $purchaseAvailability,
        private readonly SaveSalesDocumentDraft $saveSalesDraft,
        private readonly SavePurchaseDocumentDraft $savePurchaseDraft,
        private readonly GenerateDocumentNumber $numbers,
        private readonly AdjustReservedBalance $adjustReserved,
    ) {}

    /**
     * @return array{
     *   sales_order_map:array<int,int>,
     *   purchase_order_map:array<int,int>,
     *   sales_orders:int,
     *   purchase_orders:int,
     *   reservations:int
     * }
     */
    public function handle(Period $source, Period $target): array
    {
        $this->assertPeriods($source, $target);
        PeriodContext::ensureWritable();

        if ((int) PeriodContext::periodId() !== (int) $target->id) {
            throw new DomainException('CarryOpenOrders hedef period context içinde çalışmalıdır.');
        }

        $snapshot = PeriodContext::withinSystem(
            $source,
            fn (): array => $this->sourceSnapshot(),
        );

        $salesMap = [];
        $purchaseMap = [];
        $reservationCount = 0;

        foreach ($snapshot['sales_orders'] as $order) {
            $targetOrder = $this->carryOrder($source, $target, $order, DocumentType::SalesOrder);
            $salesMap[$order['id']] = (int) $targetOrder->id;
            $reservationCount += $this->restoreReservations($order, $targetOrder);
        }

        foreach ($snapshot['purchase_orders'] as $order) {
            $targetOrder = $this->carryOrder($source, $target, $order, DocumentType::PurchaseOrder);
            $purchaseMap[$order['id']] = (int) $targetOrder->id;
        }

        AuditContext::period(
            'K-256 açık sipariş dönem devri tamamlandı.',
            [
                'source_period_id' => (int) $source->id,
                'target_period_id' => (int) $target->id,
                'sales_orders' => count($salesMap),
                'purchase_orders' => count($purchaseMap),
                'reservations' => $reservationCount,
            ],
            null,
            'period_open_orders_carried',
        );

        return [
            'sales_order_map' => $salesMap,
            'purchase_order_map' => $purchaseMap,
            'sales_orders' => count($salesMap),
            'purchase_orders' => count($purchaseMap),
            'reservations' => $reservationCount,
        ];
    }

    /** @return array{sales_orders:list<array<string,mixed>>,purchase_orders:list<array<string,mixed>>} */
    private function sourceSnapshot(): array
    {
        return [
            'sales_orders' => $this->sourceOrders(DocumentType::SalesOrder),
            'purchase_orders' => $this->sourceOrders(DocumentType::PurchaseOrder),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function sourceOrders(DocumentType $type): array
    {
        $orders = Document::query()
            ->with('lines')
            ->where('document_type', $type->value)
            ->whereIn('status', $type === DocumentType::SalesOrder ? ['confirmed'] : ['approved', 'sent'])
            ->orderBy('id')
            ->get();

        $result = [];

        foreach ($orders as $order) {
            $lines = [];

            foreach ($order->lines as $line) {
                $remaining = $type === DocumentType::SalesOrder
                    ? $this->salesAvailability->orderRemaining($line)
                    : bcsub(
                        $this->purchaseAvailability->orderReceiptRemaining($line),
                        (string) $line->cancelled_quantity,
                        3,
                    );

                if (bccomp($remaining, '0', 3) <= 0) {
                    continue;
                }

                $reservations = [];

                if ($type === DocumentType::SalesOrder && $line->line_kind === 'stock') {
                    $reservations = DB::connection('period')
                        ->table('stock_reservations')
                        ->where('document_line_id', $line->id)
                        ->where('status', 'active')
                        ->orderBy('location_id')
                        ->orderBy('id')
                        ->get()
                        ->map(fn (object $reservation): array => [
                            'location_id' => (int) $reservation->location_id,
                            'quantity' => (string) $reservation->quantity,
                        ])
                        ->all();
                }

                $lines[] = [
                    'source_line_id' => (int) $line->id,
                    'line_kind' => (string) $line->line_kind,
                    'product_id' => $line->product_id !== null ? (int) $line->product_id : null,
                    'description' => $line->description,
                    'unit_id' => $line->unit_id !== null ? (int) $line->unit_id : null,
                    'quantity' => $remaining,
                    'conversion_factor' => $line->conversion_factor !== null
                        ? (string) $line->conversion_factor
                        : null,
                    'location_id' => $line->location_id !== null ? (int) $line->location_id : null,
                    'unit_price' => (string) $line->unit_price,
                    'line_discount_rate' => (string) $line->line_discount_rate,
                    'line_discount_amount' => '0.0000',
                    'vat_rate' => (string) $line->vat_rate,
                    'reserve_stock' => (bool) $line->reserve_stock,
                    'configuration' => $line->configuration,
                    'reservations' => $reservations,
                ];
            }

            if ($lines === []) {
                continue;
            }

            $result[] = [
                'id' => (int) $order->id,
                'number' => (string) $order->number,
                'contact_id' => $order->contact_id !== null ? (int) $order->contact_id : null,
                'currency' => (string) $order->currency,
                'exchange_rate' => (string) $order->exchange_rate,
                'due_date' => $order->due_date?->toDateString(),
                'discount_rate' => (string) $order->discount_rate,
                'discount_amount' => '0.0000',
                'requirements_snapshot' => $order->requirements_snapshot,
                'notes' => $order->notes,
                'lines' => $lines,
            ];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $sourceOrder
     */
    private function carryOrder(
        Period $source,
        Period $target,
        array $sourceOrder,
        DocumentType $type,
    ): Document {
        $existing = DB::connection('period')
            ->table('period_document_carries')
            ->where('source_period_id', $source->id)
            ->where('source_document_id', $sourceOrder['id'])
            ->first();

        if ($existing) {
            if ((string) $existing->document_type !== $type->value
                || (string) $existing->source_document_number !== (string) $sourceOrder['number']) {
                throw new DomainException(
                    "Carry provenance source document #{$sourceOrder['id']} ile uyuşmuyor.",
                );
            }

            return Document::query()
                ->where('document_type', $type->value)
                ->findOrFail((int) $existing->target_document_id);
        }

        return DB::connection('period')->transaction(function () use (
            $source,
            $target,
            $sourceOrder,
            $type,
        ): Document {
            $lines = array_map(
                fn (array $line): array => [
                    'line_kind' => $line['line_kind'],
                    'product_id' => $line['product_id'],
                    'description' => $line['description'],
                    'unit_id' => $line['unit_id'],
                    'quantity' => $line['quantity'],
                    'conversion_factor' => $line['conversion_factor'],
                    'location_id' => $line['location_id'],
                    'unit_price' => $line['unit_price'],
                    'line_discount_rate' => $line['line_discount_rate'],
                    'line_discount_amount' => $line['line_discount_amount'],
                    'vat_rate' => $line['vat_rate'],
                    'reserve_stock' => $line['reserve_stock'],
                    'configuration' => $line['configuration'],
                    'source_line_id' => null,
                ],
                $sourceOrder['lines'],
            );

            $header = [
                'document_date' => $target->starts_on->toDateString(),
                'due_date' => $sourceOrder['due_date'],
                'contact_id' => $sourceOrder['contact_id'],
                'currency' => $sourceOrder['currency'],
                'exchange_rate' => $sourceOrder['exchange_rate'],
                'discount_rate' => $sourceOrder['discount_rate'],
                'discount_amount' => $sourceOrder['discount_amount'],
                'requirements_snapshot' => $sourceOrder['requirements_snapshot'],
                'notes' => $sourceOrder['notes'],
            ];

            $targetOrder = $type === DocumentType::SalesOrder
                ? $this->saveSalesDraft->handle($type, $header, $lines)
                : $this->savePurchaseDraft->handle($type, $header, $lines);

            $targetOrder->number = $this->numbers->handle($type->value, (int) $target->year);
            $targetOrder->status = $type === DocumentType::SalesOrder ? 'confirmed' : 'approved';
            $targetOrder->version = (int) $targetOrder->version + 1;
            $targetOrder->save();

            DB::connection('period')->table('period_document_carries')->insert([
                'source_period_id' => (int) $source->id,
                'source_document_id' => (int) $sourceOrder['id'],
                'source_document_number' => (string) $sourceOrder['number'],
                'target_document_id' => (int) $targetOrder->id,
                'document_type' => $type->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $targetOrder->load('lines')->refresh();
        }, attempts: 3);
    }

    /**
     * @param array<string,mixed> $sourceOrder
     */
    private function restoreReservations(array $sourceOrder, Document $targetOrder): int
    {
        $targetOrder->loadMissing('lines');
        $targetLines = $targetOrder->lines->values();

        if ($targetLines->count() !== count($sourceOrder['lines'])) {
            throw new DomainException(
                "Carried sales_order #{$targetOrder->id} satır sayısı source snapshot ile uyuşmuyor.",
            );
        }

        $count = 0;
        $actor = auth()->user();

        foreach ($sourceOrder['lines'] as $index => $sourceLine) {
            $targetLine = $targetLines[$index];

            if ($sourceLine['line_kind'] !== 'stock' || $sourceLine['reservations'] === []) {
                continue;
            }

            foreach ($sourceLine['reservations'] as $reservation) {
                $exists = StockReservation::query()
                    ->where('document_type', DocumentType::SalesOrder->value)
                    ->where('document_id', $targetOrder->id)
                    ->where('document_line_id', $targetLine->id)
                    ->where('product_id', $targetLine->product_id)
                    ->where('location_id', $reservation['location_id'])
                    ->where('quantity', $reservation['quantity'])
                    ->where('status', 'active')
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::connection('period')->transaction(function () use (
                    $targetOrder,
                    $targetLine,
                    $reservation,
                    $actor,
                ): void {
                    $this->adjustReserved->handle(
                        (int) $targetLine->product_id,
                        (int) $reservation['location_id'],
                        (string) $reservation['quantity'],
                    );

                    StockReservation::query()->create([
                        'product_id' => (int) $targetLine->product_id,
                        'location_id' => (int) $reservation['location_id'],
                        'quantity' => (string) $reservation['quantity'],
                        'document_type' => DocumentType::SalesOrder->value,
                        'document_id' => (int) $targetOrder->id,
                        'document_line_id' => (int) $targetLine->id,
                        'status' => 'active',
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);
                }, attempts: 3);

                $count++;
            }
        }

        return $count;
    }

    private function assertPeriods(Period $source, Period $target): void
    {
        if ((int) $source->company_id !== (int) $target->company_id
            || (int) $target->year !== (int) $source->year + 1) {
            throw new DomainException('K-256 order carry yalnız aynı şirketin ardışık dönemleri arasında yapılabilir.');
        }
    }
}
