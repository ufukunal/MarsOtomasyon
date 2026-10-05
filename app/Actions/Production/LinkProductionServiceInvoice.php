<?php

namespace App\Actions\Production;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\ProductionOrder;
use App\Models\Period\ProductionServiceInvoice;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Production\ProductionServiceCostAllocator;
use DomainException;
use Illuminate\Support\Facades\DB;

final class LinkProductionServiceInvoice
{
    public function __construct(private readonly ProductionServiceCostAllocator $allocator) {}

    public function handle(
        ProductionOrder $order,
        Document $invoice,
        string $idempotencyKey,
    ): ProductionServiceInvoice {
        MutationAuthorizer::authorize('subcontracting.update');

        $id = IdempotencyKey::run(
            $idempotencyKey,
            'production-service-invoice.link:'.$order->id.':'.$invoice->id,
            fn (): int => DB::connection('period')->transaction(function () use ($order, $invoice): int {
                $lockedOrder = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);
                $lockedInvoice = Document::query()->with('lines')->lockForUpdate()->findOrFail($invoice->id);

                if ($lockedOrder->production_type !== 'subcontract'
                    || ! in_array($lockedOrder->status, ['confirmed', 'in_progress', 'completed'], true)) {
                    throw new DomainException('Hizmet faturası yalnız açık/tamamlanmış fason üretim emrine bağlanabilir.');
                }

                if ($lockedInvoice->document_type !== DocumentType::SupplierInvoice
                    || $lockedInvoice->status !== 'posted'
                    || (int) $lockedInvoice->contact_id !== (int) $lockedOrder->subcontractor_contact_id
                    || $lockedInvoice->lines->where('line_kind', 'service')->isEmpty()) {
                    throw new DomainException('Fason hizmet faturası supplier/service/status kurallarıyla eşleşmiyor.');
                }

                $mapping = ProductionServiceInvoice::query()->firstOrCreate([
                    'production_order_id' => $lockedOrder->id,
                    'purchase_invoice_id' => $lockedInvoice->id,
                ]);

                $this->allocator->recalculateInvoice(
                    $lockedInvoice,
                    $lockedInvoice->document_date->toDateString(),
                );

                AuditContext::period(
                    'Fason hizmet faturası üretim emrine bağlandı.',
                    [
                        'production_order_id' => $lockedOrder->id,
                        'purchase_invoice_id' => $lockedInvoice->id,
                    ],
                    $lockedOrder,
                    'production_service_invoice_linked',
                );

                return (int) $mapping->id;
            }, attempts: 3),
        );

        return ProductionServiceInvoice::query()->findOrFail((int) $id);
    }
}
