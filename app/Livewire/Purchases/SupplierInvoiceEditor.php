<?php

namespace App\Livewire\Purchases;

use App\Actions\Documents\ReverseDocument;
use App\Actions\Purchases\PostSupplierInvoice;
use App\Enums\DocumentType;
use App\Models\Period\PurchaseMatch;
use Illuminate\Contracts\View\View;

class SupplierInvoiceEditor extends BasePurchaseDocumentEditor
{
    public bool $deviationAccepted = false;

    public string $reversalDate = '';

    public string $reversalReason = '';

    protected function documentType(): DocumentType
    {
        return DocumentType::SupplierInvoice;
    }

    protected function permissionPrefix(): string
    {
        return 'supplier_invoices';
    }

    protected function pageTitle(): string
    {
        return 'Alış Faturası';
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

    public function post(PostSupplierInvoice $action): void
    {
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle(
            $this->document,
            $this->mutationKey('post'),
            $this->deviationAccepted,
        )->load('lines'));
        $this->completeMutation('post');
    }

    public function render(): View
    {
        $view = parent::render();

        $matches = $this->document
            ? PurchaseMatch::query()
                ->whereHas('supplierInvoiceLine', fn ($query) => $query
                    ->where('document_id', $this->document?->id))
                ->orderBy('supplier_invoice_line_id')
                ->get()
            : collect();

        return $view->with('purchaseMatches', $matches);
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

        return $this->redirectRoute('purchases.invoices.edit', ['id' => $reversal->id], navigate: false);
    }
}
