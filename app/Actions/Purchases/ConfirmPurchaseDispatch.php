<?php

namespace App\Actions\Purchases;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;

final class ConfirmPurchaseDispatch
{
    public function __construct(
        private readonly GenerateDocumentNumber $numbers,
        private readonly EnsurePeriodOpen $period,
    ) {}

    public function handle(Document $dispatch, string $key): Document
    {
        MutationAuthorizer::authorize('purchase_dispatches.update');

        return IdempotencyKey::run($key, 'purchase-dispatch.confirm:'.$dispatch->id, function () use ($dispatch): Document {
            $locked = Document::query()->with(['lines', 'incomingRelations.sourceDocument'])
                ->lockForUpdate()->findOrFail($dispatch->id);
            if ($locked->document_type !== DocumentType::PurchaseDispatch || $locked->status !== 'draft' || $locked->lines->isEmpty()) {
                throw new DomainException('Yalnız satırlı taslak alış irsaliyesi onaylanabilir.');
            }

            $order = $locked->incomingRelations->firstWhere('relation_type', 'purchase_order_to_purchase_dispatch')?->sourceDocument;
            if (! $order || $order->document_type !== DocumentType::PurchaseOrder
                || ! in_array($order->status, ['approved', 'sent'], true)) {
                throw new DomainException('Alış irsaliyesinin onaylı kaynak siparişi bulunamadı.');
            }

            foreach ($locked->lines as $line) {
                if (! $order->lines()->whereKey($line->source_line_id)->exists()) {
                    throw new DomainException('İrsaliye kalemi kaynak siparişle uyuşmuyor.');
                }
            }

            $this->period->handle(CarbonImmutable::parse($locked->document_date));
            $locked->number = $this->numbers->handle('purchase_dispatch', $locked->document_date->year);
            $locked->status = 'confirmed';
            $locked->version = (int) $locked->version + 1;
            $locked->save();

            AuditContext::period('Alış irsaliyesi onaylandı (stok etkisi yok).', [
                'dispatch_id' => $locked->id, 'source_order_id' => $order->id,
            ], $locked, 'purchase_dispatch_confirmed');

            return $locked->refresh();
        });
    }
}
