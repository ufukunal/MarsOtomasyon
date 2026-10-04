<?php

namespace App\Actions\Purchases;

use App\Actions\Documents\PostDocument;
use App\DataObjects\Documents\DocumentPostingContext;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class PostSupplierInvoice
{
    public function __construct(
        private readonly ThreeWayMatchSupplierInvoice $matcher,
        private readonly PostDocument $postDocument,
        private readonly ApplySupplierInvoiceCosts $costs,
    ) {}

    public function handle(
        Document $invoice,
        string $idempotencyKey,
        bool $deviationAccepted = false,
    ): Document {
        MutationAuthorizer::authorize('supplier_invoices.update');

        return IdempotencyKey::run(
            $idempotencyKey,
            'supplier-invoice.post:'.$invoice->id,
            function () use ($invoice, $idempotencyKey, $deviationAccepted): Document {
                $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($invoice->id);

                if ($locked->document_type !== DocumentType::SupplierInvoice
                    || $locked->status !== 'draft') {
                    throw new DomainException('Yalnız taslak alış faturası kesinleştirilebilir.');
                }

                $this->matcher->handle($locked);

                $contactAmountTry = bcadd(
                    bcmul((string) $locked->grand_total, (string) $locked->exchange_rate, 8),
                    '0',
                    4,
                );

                $posted = $this->postDocument->handle(
                    $locked,
                    hash('sha256', $idempotencyKey.':document-post'),
                    new DocumentPostingContext(
                        contactAmount: $contactAmountTry,
                        contactCurrency: 'TRY',
                    ),
                );

                $this->costs->handle($posted, $deviationAccepted);

                return $posted->refresh();
            },
        );
    }
}
