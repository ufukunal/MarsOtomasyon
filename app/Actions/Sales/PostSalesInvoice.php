<?php

namespace App\Actions\Sales;

use App\Actions\Documents\PostDocument;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Auth\MutationAuthorizer;
use DomainException;

final class PostSalesInvoice
{
    public function __construct(private readonly PostDocument $postDocument) {}

    public function handle(Document $invoice, string $idempotencyKey): Document
    {
        MutationAuthorizer::authorize('sales_invoices.update');

        if ($invoice->document_type !== DocumentType::SalesInvoice) {
            throw new DomainException('Yalnız satış faturası PostSalesInvoice ile kesinleştirilebilir.');
        }

        return $this->postDocument->handle($invoice, $idempotencyKey);
    }
}
