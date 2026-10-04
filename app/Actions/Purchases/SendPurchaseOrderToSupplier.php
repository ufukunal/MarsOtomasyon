<?php

namespace App\Actions\Purchases;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class SendPurchaseOrderToSupplier
{
    public function handle(Document $order, string $idempotencyKey): Document
    {
        MutationAuthorizer::authorize('purchase_orders.update');

        return IdempotencyKey::run(
            $idempotencyKey,
            'purchase-order.send:'.$order->id,
            function () use ($order): Document {
                $locked = Document::query()->lockForUpdate()->findOrFail($order->id);

                if ($locked->document_type !== DocumentType::PurchaseOrder
                    || $locked->status !== 'approved'
                    || $locked->number === null) {
                    throw new DomainException('Yalnız onaylı ve numaralanmış satınalma siparişi tedarikçiye gönderilebilir.');
                }

                $locked->status = 'sent';
                $locked->version = (int) $locked->version + 1;
                $locked->save();

                AuditContext::period(
                    'Satınalma siparişi tedarikçiye gönderildi.',
                    ['purchase_order_id' => $locked->id, 'number' => $locked->number],
                    $locked,
                    'purchase_order_sent',
                );

                return $locked->refresh();
            },
        );
    }
}
