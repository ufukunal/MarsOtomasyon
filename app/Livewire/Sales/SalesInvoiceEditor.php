<?php

namespace App\Livewire\Sales;

use App\Actions\Documents\ReverseDocument;
use App\Actions\Sales\PostSalesInvoice;
use App\Enums\DocumentType;

class SalesInvoiceEditor extends BaseSalesDocumentEditor
{
    public string $reversalDate = '';

    public string $reversalReason = '';

    protected function documentType(): DocumentType
    {
        return DocumentType::SalesInvoice;
    }

    protected function permissionPrefix(): string
    {
        return 'sales_invoices';
    }

    protected function pageTitle(): string
    {
        return $this->document ? 'Satış Faturası' : 'Yeni Satış Faturası';
    }

    protected function extraMutationNames(): array
    {
        return ['post', 'reverse'];
    }

    public function mount(?int $id = null): void
    {
        parent::mount($id);
        $this->reversalDate = now()->toDateString();
    }

    public function post(PostSalesInvoice $action): void
    {
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle($this->document, $this->mutationKey('post'))->load('lines'));
        $this->completeMutation('post');
    }

    public function reverse(ReverseDocument $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $reversal = $action->handle(
            $this->document,
            $this->reversalDate,
            $this->reversalReason,
            $this->mutationKey('reverse'),
        );
        $this->completeMutation('reverse');

        return $this->redirectRoute('sales.invoices.edit', ['id' => $reversal->id], navigate: false);
    }
}
