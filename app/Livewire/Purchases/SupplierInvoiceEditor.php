<?php

namespace App\Livewire\Purchases;

use App\Actions\Documents\ReverseDocument;
use App\Actions\Purchases\SavePurchaseDocumentDraft;
use App\Enums\DocumentType;
use App\Models\Period\PurchaseMatch;
use Illuminate\Contracts\View\View;

class SupplierInvoiceEditor extends BasePurchaseDocumentEditor
{
    /** @return list<string> */
    protected function allowedRecordDeletionActions(): array
    {
        return ['deleteInvoice'];
    }

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
        return ['post', 'reverse', 'invoiceEdit', 'invoiceDelete'];
    }

    public function mount(?int $id = null): void
    {
        parent::mount($id);

        // Default only new/unspecified draft invoices; retain saved choices.
        if ($this->document === null || $this->document->status === 'draft') {
            $this->uiMeta['payment_method'] ??= 'Vadeli';
        }

        $this->reversalDate = now()->toDateString();
    }

    protected function resetPrototypeTypeFields(): void
    {
        parent::resetPrototypeTypeFields();
        $this->uiMeta['payment_method'] = 'Vadeli';
    }

    /** The single visible Save button also posts the invoice to the ledger. */
    public function save(SavePurchaseDocumentDraft $action): void
    {
        // Posting requires update permission even when saving a brand-new invoice.
        abort_unless(auth()->user()?->can($this->permissionPrefix().'.update'), 403);
        parent::save($action);
        $this->post(app(\App\Actions\Documents\ManageInvoiceRecord::class));
    }

    public function post(\App\Actions\Documents\ManageInvoiceRecord $action): void
    {
        $this->requireSavedDraft();
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->save(
            $this->document,
            $this->mutationKey('post'),
            deviationAccepted: $this->deviationAccepted,
        )->load('lines'));
        $this->completeMutation('post');
    }

    public function render(): View
    {
        $view = parent::render();

        $matches = $this->document
            ? PurchaseMatch::query()
                ->whereHas('supplierInvoiceLine', fn ($query) => $query
                    ->where('document_id', $this->document->id))
                ->orderBy('supplier_invoice_line_id')
                ->get()
            : collect();

        return $view->with('purchaseMatches', $matches);
    }

    /**
     * One-click invoice edit: keep the posted ledger immutable, open a safe
     * editable draft after its internal reversal.
     */
    public function editInvoice(\App\Actions\Documents\ManageInvoiceRecord $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $draft = $action->edit($this->document, $this->mutationKey('invoiceEdit'));
        $this->completeMutation('invoiceEdit');

        return $this->redirectRoute('purchases.invoices.edit', ['id' => $draft->id], navigate: false);
    }

    public function deleteInvoice(\App\Actions\Documents\ManageInvoiceRecord $action): mixed
    {
        $this->requireVerifiedRecordDeletion('deleteInvoice');
        abort_unless($this->document !== null, 422);
        $action->delete($this->document, $this->mutationKey('invoiceDelete'));
        $this->completeMutation('invoiceDelete');

        return $this->redirectRoute('purchases.invoices.index', navigate: false);
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
