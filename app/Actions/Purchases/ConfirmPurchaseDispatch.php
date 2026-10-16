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
        private readonly PurchaseLineAvailability $availability,
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
                || ! in_array($order->status, ['approved', 'sent'], true)
                || (int) $locked->contact_id !== (int) $order->contact_id
                || (string) $locked->currency !== (string) $order->currency) {
                throw new DomainException('İrsaliye tedarikçisi, dövizi veya kaynak siparişi uyuşmuyor.');
            }

            foreach ($locked->lines as $line) {
                $sourceLine = $order->lines()->find($line->source_line_id);
                if ($sourceLine === null
                    || $line->line_kind !== $sourceLine->line_kind
                    || $line->product_id !== $sourceLine->product_id
                    || $line->unit_id !== $sourceLine->unit_id
                    || bccomp((string) $line->quantity, '0', 3) <= 0
                    || bccomp($this->availability->orderDispatchRemaining($sourceLine), '0', 3) < 0) {
                    throw new DomainException('İrsaliye satırı veya ayrılan toplam miktar kaynak siparişle uyuşmuyor.');
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
