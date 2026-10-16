<?php

namespace App\Actions\Purchases;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateGoodsReceiptFromPurchaseDispatch
{
    public function __construct(private readonly CreateGoodsReceiptFromOrder $fromOrder) {}

    /**
     * @param  array<int,string>  $lineQuantities
     * @param  array<int,int>  $lineLocationIds
     */
    public function handle(Document $dispatch, array $lineQuantities, array $lineLocationIds, string $date, string $key): Document
    {
        MutationAuthorizer::authorize('purchase_dispatches.update');
        MutationAuthorizer::authorize('goods_receipts.create');

        return IdempotencyKey::run($key, 'purchase-dispatch.to-receipt:'.$dispatch->id, function () use (
            $dispatch, $lineQuantities, $lineLocationIds, $date, $key
        ): Document {
            $locked = Document::query()->with('incomingRelations.sourceDocument')
                ->lockForUpdate()->findOrFail($dispatch->id);
            if ($locked->document_type !== DocumentType::PurchaseDispatch || $locked->status !== 'confirmed') {
                throw new DomainException('Yalnız onaylı alış irsaliyesinden mal kabul oluşturulabilir.');
            }
            $order = $locked->incomingRelations->firstWhere('relation_type', 'purchase_order_to_purchase_dispatch')?->sourceDocument;
            if (! $order || $order->document_type !== DocumentType::PurchaseOrder) {
                throw new DomainException('İrsaliyenin kaynak siparişi bulunamadı.');
            }

            $usedReceiptIds = DocumentRelation::query()
                ->where('source_document_id', $locked->id)
                ->where('relation_type', 'purchase_dispatch_to_goods_receipt')
                ->pluck('target_document_id');

            $orderQuantities = [];
            $orderLocations = [];
            foreach ($lineQuantities as $lineId => $quantity) {
                $line = DocumentLine::query()->where('document_id', $locked->id)
                    ->lockForUpdate()->findOrFail((int) $lineId);
                if ($line->source_line_id === null || ! $order->lines()->whereKey($line->source_line_id)->exists()) {
                    throw new DomainException('İrsaliye satırının kaynak sipariş karşılığı yok.');
                }
                $requested = bcadd((string) $quantity, '0', 3);
                $used = $usedReceiptIds->isEmpty() ? '0.000' : (string) DB::connection('period')
                    ->table('document_lines')->whereIn('document_id', $usedReceiptIds)
                    ->where('source_line_id', $line->source_line_id)->sum('quantity');

                $remaining = bcsub((string) $line->quantity, $used, 3);
                if (bccomp($requested, '0', 3) <= 0 || bccomp($requested, $remaining, 3) > 0) {
                    throw new DomainException('Mal kabul miktarı irsaliye kalanını aşıyor.');
                }

                $sourceId = (int) $line->source_line_id;
                $orderQuantities[$sourceId] = bcadd($orderQuantities[$sourceId] ?? '0', $requested, 3);
                if (isset($lineLocationIds[(int) $line->id])) {
                    $orderLocations[$sourceId] = $lineLocationIds[(int) $line->id];
                }
            }

            if ($orderQuantities === []) {
                throw new DomainException('Mal kabul satırı seçilmedi.');
            }

            $receipt = $this->fromOrder->handle($order, $orderQuantities, $orderLocations, $date, $key.'-order', null, true);
            $actor = auth()->user();
            DocumentRelation::query()->create([
                'source_document_id' => $locked->id,
                'target_document_id' => $receipt->id,
                'relation_type' => 'purchase_dispatch_to_goods_receipt',
                'created_by' => $actor?->id,
                'created_by_name' => $actor?->name,
            ]);

            return $receipt;
        });
    }
}
