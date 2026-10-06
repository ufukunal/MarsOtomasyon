<?php

namespace App\Livewire\Purchases;

use App\Actions\Documents\ReverseDocument;
use App\Actions\Purchases\CreateSupplierInvoiceFromReceipts;
use App\Actions\Purchases\PostGoodsReceipt;
use App\Actions\Purchases\PurchaseLineAvailability;
use App\Enums\DocumentType;

class GoodsReceiptEditor extends BasePurchaseDocumentEditor
{
    public string $invoiceDate = '';

    public string $invoiceCurrency = 'TRY';

    public string $invoiceExchangeRate = '1.000000';

    public string $reversalDate = '';

    public string $reversalReason = '';

    /** @var array<int,string> */
    public array $invoiceQuantities = [];

    protected function documentType(): DocumentType
    {
        return DocumentType::GoodsReceipt;
    }

    protected function permissionPrefix(): string
    {
        return 'goods_receipts';
    }

    protected function pageTitle(): string
    {
        return 'Mal Kabul';
    }

    protected function extraMutationNames(): array
    {
        return ['post', 'invoice', 'reverse'];
    }

    public function mount(?int $id = null): void
    {
        parent::mount($id);
        $this->invoiceDate = now()->toDateString();
        $this->reversalDate = now()->toDateString();

        if ($this->document) {
            $this->invoiceCurrency = (string) $this->document->currency;
            $this->invoiceExchangeRate = (string) $this->document->exchange_rate;
        }

        $this->syncInvoiceInputs();
    }

    public function post(PostGoodsReceipt $action): void
    {
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle(
            $this->document,
            $this->mutationKey('post'),
        )->load('lines'));
        $this->completeMutation('post');
        $this->syncInvoiceInputs();
    }

    public function createInvoice(CreateSupplierInvoiceFromReceipts $action): mixed
    {
        abort_unless($this->document !== null, 422);

        $quantities = array_filter(
            $this->invoiceQuantities,
            fn (string $quantity): bool => bccomp($quantity, '0', 3) > 0,
        );

        $invoice = $action->handle(
            $quantities,
            $this->invoiceDate,
            $this->invoiceCurrency,
            $this->invoiceExchangeRate,
            $this->mutationKey('invoice'),
        );
        $this->completeMutation('invoice');

        return $this->redirectRoute('purchases.invoices.edit', ['id' => $invoice->id], navigate: false);
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

        return $this->redirectRoute('purchases.receipts.edit', ['id' => $reversal->id], navigate: false);
    }

    private function syncInvoiceInputs(): void
    {
        if (! $this->document || $this->document->status !== 'posted') {
            return;
        }

        $this->document->load('lines');
        $availability = app(PurchaseLineAvailability::class);

        foreach ($this->document->lines as $line) {
            $this->invoiceQuantities[$line->id] = $availability->receiptInvoiceRemaining($line);
        }
    }
}
