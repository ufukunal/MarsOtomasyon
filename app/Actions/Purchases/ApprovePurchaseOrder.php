<?php

namespace App\Actions\Purchases;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class ApprovePurchaseOrder
{
    public function __construct(private readonly GenerateDocumentNumber $numbers) {}

    public function handle(Document $order, string $idempotencyKey): Document
    {
        MutationAuthorizer::authorize('purchase_orders.approve');

        return IdempotencyKey::run(
            $idempotencyKey,
            'purchase-order.approve:'.$order->id,
            function () use ($order): Document {
                $locked = Document::query()->lockForUpdate()->findOrFail($order->id);

                if ($locked->document_type !== DocumentType::PurchaseOrder
                    || $locked->status !== 'pending_approval') {
                    throw new DomainException('Satınalma siparişi bu durumda onaylanamaz.');
                }

                if ($locked->number === null) {
                    $locked->number = $this->numbers->handle(
                        DocumentType::PurchaseOrder->value,
                        $locked->document_date->year,
                    );
                }

                $locked->status = 'approved';
                $locked->version = (int) $locked->version + 1;
                $locked->save();

                AuditContext::period(
                    'Satınalma siparişi onaylandı.',
                    ['purchase_order_id' => $locked->id, 'number' => $locked->number],
                    $locked,
                    'purchase_order_approved',
                );

                return $locked->refresh();
            },
        );
    }
}
