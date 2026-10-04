<?php

namespace App\Actions\Purchases;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;

final class SubmitPurchaseOrderForApproval
{
    public function __construct(private readonly EnsurePeriodOpen $ensurePeriodOpen) {}

    public function handle(Document $order, string $idempotencyKey): Document
    {
        MutationAuthorizer::authorize('purchase_orders.update');

        return IdempotencyKey::run(
            $idempotencyKey,
            'purchase-order.submit:'.$order->id,
            function () use ($order): Document {
                $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($order->id);

                if ($locked->document_type !== DocumentType::PurchaseOrder
                    || $locked->status !== 'draft'
                    || $locked->lines->isEmpty()) {
                    throw new DomainException('Yalnız satırlı taslak satınalma siparişi onaya gönderilebilir.');
                }

                $this->ensurePeriodOpen->handle(CarbonImmutable::parse($locked->document_date));
                $locked->status = 'pending_approval';
                $locked->version = (int) $locked->version + 1;
                $locked->save();

                AuditContext::period(
                    'Satınalma siparişi onaya gönderildi.',
                    ['purchase_order_id' => $locked->id],
                    $locked,
                    'purchase_order_submitted',
                );

                return $locked->refresh();
            },
        );
    }
}
