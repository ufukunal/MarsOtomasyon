<?php

namespace App\Actions\Sales;

use App\Actions\Documents\PostDocument;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Auth\MutationAuthorizer;
use DomainException;

final class PostSalesInvoice
{
    public function __construct(
        private readonly PostDocument $postDocument,
        private readonly CalculateSalesRiskProjection $riskProjection,
    ) {}

    public function handle(Document $invoice, string $idempotencyKey, bool $riskAccepted = false): Document
    {
        MutationAuthorizer::authorize('sales_invoices.update');

        if ($invoice->document_type !== DocumentType::SalesInvoice) {
            throw new DomainException('Yalnız satış faturası PostSalesInvoice ile kesinleştirilebilir.');
        }

        // A sales invoice adds a customer debit when it is posted. Warn on the
        // real TRY exposure before allowing the irreversible ledger movement.
        // Non-TRY totals must never be added to a TRY-denominated risk limit.
        if ($invoice->currency === 'TRY' && $invoice->contact_id !== null
            && Document::query()->whereKey($invoice->getKey())->value('status') !== 'posted') {
            $projection = $this->riskProjection->handle($invoice);
            if ($projection->knownLimitExceeded && ! $riskAccepted) {
                throw new DomainException(
                    'Cari risk limiti aşılıyor. Satış faturası kesinleştirilmeden önce risk uyarısını açıkça kabul edin.',
                );
            }
        }

        return $this->postDocument->handle($invoice, $idempotencyKey);
    }
}
