<?php

namespace App\Livewire\Purchases;

use App\Actions\Purchases\ApprovePurchaseQuote;
use App\Actions\Purchases\ConvertPurchaseQuoteToOrder;
use App\Enums\DocumentType;

class PurchaseQuoteEditor extends BasePurchaseDocumentEditor
{
    protected function documentType(): DocumentType
    {
        return DocumentType::PurchaseQuote;
    }

    protected function permissionPrefix(): string
    {
        return 'purchase_quotes';
    }

    protected function pageTitle(): string
    {
        return $this->document ? 'Alış Teklifi' : 'Yeni Alış Teklifi';
    }

    /** @return list<string> */
    protected function extraMutationNames(): array
    {
        return ['approveQuote', 'convertToOrder'];
    }

    public function approveQuote(ApprovePurchaseQuote $action): void
    {
        $this->requireSavedDraft();
        abort_unless($this->document !== null, 422);

        $approved = $action->handle($this->document, $this->mutationKey('approveQuote'));
        $this->loadDocument($approved->load('lines'));
        $this->completeMutation('approveQuote');
    }

    public function convertToOrder(ConvertPurchaseQuoteToOrder $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $order = $action->handle($this->document, $this->mutationKey('convertToOrder'));
        $this->completeMutation('convertToOrder');

        return $this->redirectRoute('purchases.orders.edit', ['id' => $order->id], navigate: false);
    }
}
