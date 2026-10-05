<?php

namespace App\Actions\Sales;

use App\Actions\Documents\CalculateDocumentTotals;
use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Actions\Production\CreateProductionOrdersForSalesOrder;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;

final class ConfirmSalesOrder
{
    public function __construct(
        private readonly CalculateDocumentTotals $calculator,
        private readonly CalculateSalesRiskProjection $risk,
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly GenerateDocumentNumber $numbers,
        private readonly CreateProductionOrdersForSalesOrder $productionOrders,
    ) {}

    public function handle(Document $order, string $idempotencyKey, bool $riskAccepted = false): Document
    {
        MutationAuthorizer::authorize('sales_orders.update');

        return IdempotencyKey::run(
            $idempotencyKey,
            'sales-order.confirm:'.$order->id,
            function () use ($order, $riskAccepted): Document {
                $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($order->id);

                if ($locked->document_type !== DocumentType::SalesOrder || $locked->status !== 'draft') {
                    throw new DomainException('Yalnız taslak satış siparişi onaylanabilir.');
                }

                $this->ensurePeriodOpen->handle(CarbonImmutable::parse($locked->document_date));

                $totals = $this->calculator->handle(
                    $locked->lines->map(fn ($line) => [
                        'quantity' => (string) $line->quantity,
                        'unit_price' => (string) $line->unit_price,
                        'line_discount_rate' => (string) $line->line_discount_rate,
                        'line_discount_amount' => (string) $line->line_discount_amount,
                        'vat_rate' => (string) $line->vat_rate,
                    ])->all(),
                    (string) $locked->discount_rate,
                    (string) $locked->discount_amount,
                );

                foreach ($locked->lines->values() as $index => $line) {
                    $calculated = $totals->lines[$index];
                    $line->line_discount_rate = $calculated->discountRate;
                    $line->line_discount_amount = $calculated->discountAmount;
                    $line->line_total = $calculated->lineTotal;
                    $line->version = (int) $line->version + 1;
                    $line->save();
                }

                $locked->discount_rate = $totals->discountRate;
                $locked->discount_amount = $totals->discountAmount;
                $locked->subtotal = $totals->subtotal;
                $locked->tax_base = $totals->taxBase;
                $locked->vat_amount = $totals->vatAmount;
                $locked->rounding_difference = $totals->roundingDifference;
                $locked->grand_total = $totals->grandTotal;

                $projection = $this->risk->handle($locked);

                if ($projection->knownLimitExceeded && ! $riskAccepted) {
                    throw new DomainException(
                        'Cari risk limiti aşılıyor. Kullanıcı uyarıyı açıkça kabul etmelidir.',
                    );
                }

                if ($locked->number === null) {
                    $locked->number = $this->numbers->handle(
                        DocumentType::SalesOrder->value,
                        $locked->document_date->year,
                    );
                }

                $locked->status = 'confirmed';
                $locked->version = (int) $locked->version + 1;
                $locked->save();

                $productionOrderIds = $this->productionOrders->handle($locked);

                AuditContext::period(
                    'Satış siparişi onaylandı.',
                    [
                        'order_id' => $locked->id,
                        'number' => $locked->number,
                        'known_exposure' => $projection->knownExposure,
                        'security_projection_complete' => $projection->projectionComplete,
                        'risk_accepted' => $riskAccepted,
                        'production_order_ids' => $productionOrderIds,
                    ],
                    $locked,
                    'sales_order_confirmed',
                );

                return $locked->refresh();
            },
        );
    }
}
