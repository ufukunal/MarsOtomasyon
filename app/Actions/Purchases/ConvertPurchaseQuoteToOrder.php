<?php

namespace App\Actions\Purchases;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;

final class ConvertPurchaseQuoteToOrder
{
    public function __construct(private readonly EnsurePeriodOpen $period) {}

    public function handle(Document $quote, string $key): Document
    {
        MutationAuthorizer::authorize('purchase_quotes.update');
        MutationAuthorizer::authorize('purchase_orders.create');

        return IdempotencyKey::run($key, 'purchase-quote.to-order:'.$quote->id, function () use ($quote): Document {
            $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($quote->id);
            if ($locked->document_type !== DocumentType::PurchaseQuote || $locked->status !== 'approved'
                || $locked->number === null || $locked->lines->isEmpty()) {
                throw new DomainException('Yalnız numaralı, satırlı onaylı alış teklifi siparişe dönüştürülebilir.');
            }

            $this->period->handle(CarbonImmutable::parse($locked->document_date));
            $actor = auth()->user();
            $order = Document::query()->create([
                ...$locked->only([
                    'document_date', 'due_date', 'valid_until', 'contact_id',
                    'currency', 'exchange_rate', 'discount_rate', 'discount_amount',
                    'additional_discount_amount', 'subtotal', 'tax_base', 'vat_amount',
                    'rounding_difference', 'grand_total', 'requirements_snapshot', 'ui_meta', 'notes',
                ]),
                'document_type' => DocumentType::PurchaseOrder->value,
                'revision_no' => 0,
                'status' => 'draft',
                'created_by' => $actor?->id,
                'created_by_name' => $actor?->name,
            ]);

            foreach ($locked->lines as $line) {
                DocumentLine::query()->create([
                    ...$line->only([
                        'line_no', 'line_kind', 'product_id', 'description', 'unit_id', 'quantity',
                        'conversion_factor', 'base_quantity', 'location_id', 'unit_price',
                        'line_discount_rate', 'line_discount_amount', 'vat_rate', 'line_total',
                        'reserve_stock', 'configuration',
                    ]),
                    'document_id' => $order->id,
                    'cancelled_quantity' => '0.000',
                    'source_line_id' => $line->id,
                ]);
            }

            DocumentRelation::query()->create([
                'source_document_id' => $locked->id,
                'target_document_id' => $order->id,
                'relation_type' => 'purchase_quote_to_order',
                'created_by' => $actor?->id,
                'created_by_name' => $actor?->name,
            ]);

            $locked->status = 'converted';
            $locked->version = (int) $locked->version + 1;
            $locked->save();

            AuditContext::period('Alış teklifi siparişe dönüştürüldü.', [
                'purchase_quote_id' => $locked->id, 'purchase_order_id' => $order->id,
            ], $order, 'purchase_quote_converted');

            return $order->load('lines');
        });
    }
}
