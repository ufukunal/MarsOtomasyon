<?php

namespace App\Livewire\Sales;

use App\Actions\Sales\ApproveQuote;
use App\Actions\Sales\ConvertQuoteToSalesOrder;
use App\Actions\Sales\CreateQuoteRevision;
use App\Actions\Sales\IssueProforma;
use App\Actions\Sales\SendQuoteToCustomerReview;
use App\Actions\Sales\SubmitQuoteForInternalApproval;
use App\Enums\DocumentType;

class QuoteEditor extends BaseSalesDocumentEditor
{
    protected function documentType(): DocumentType { return DocumentType::Quote; }
    protected function permissionPrefix(): string { return 'quotes'; }
    protected function pageTitle(): string { return $this->document ? 'Teklif' : 'Yeni Teklif'; }

    protected function extraMutationNames(): array
    {
        return ['internalReview', 'customerReview', 'approve', 'revision', 'order', 'proforma'];
    }

    public function internalReview(SubmitQuoteForInternalApproval $action): void
    {
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle($this->document, $this->mutationKey('internalReview'))->load('lines'));
        $this->completeMutation('internalReview');
    }

    public function customerReview(SendQuoteToCustomerReview $action): void
    {
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle($this->document, $this->mutationKey('customerReview'))->load('lines'));
        $this->completeMutation('customerReview');
    }

    public function approve(ApproveQuote $action): void
    {
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle($this->document, $this->mutationKey('approve'))->load('lines'));
        $this->completeMutation('approve');
    }

    public function revision(CreateQuoteRevision $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $revision = $action->handle($this->document, $this->mutationKey('revision'));
        $this->completeMutation('revision');

        return $this->redirectRoute('sales.quotes.edit', ['id' => $revision->id], navigate: false);
    }

    public function toOrder(ConvertQuoteToSalesOrder $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $order = $action->handle($this->document, $this->mutationKey('order'));
        $this->completeMutation('order');

        return $this->redirectRoute('sales.orders.edit', ['id' => $order->id], navigate: false);
    }

    public function issueProforma(IssueProforma $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $proforma = $action->handle($this->document, $this->documentDate, $this->mutationKey('proforma'));
        $this->completeMutation('proforma');

        return $this->redirectRoute('sales.proformas.show', ['id' => $proforma->id], navigate: false);
    }
}
