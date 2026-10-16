<?php

namespace App\Livewire\Purchases;

use App\Actions\Purchases\ConfirmPurchaseDispatch;
use App\Actions\Purchases\CreateGoodsReceiptFromPurchaseDispatch;
use App\Enums\DocumentType;
use Illuminate\Support\Facades\DB;

class PurchaseDispatchEditor extends BasePurchaseDocumentEditor
{
    public string $receiptDate = '';

    /** @var array<int,string> */
    public array $receiptQuantities = [];

    /** @var array<int,int|null> */
    public array $receiptLocationIds = [];

    protected function documentType(): DocumentType
    {
        return DocumentType::PurchaseDispatch;
    }

    protected function permissionPrefix(): string
    {
        return 'purchase_dispatches';
    }

    protected function pageTitle(): string
    {
        return 'Alış İrsaliyesi';
    }

    /** @return list<string> */
    protected function extraMutationNames(): array
    {
        return ['confirmDispatch', 'createReceipt'];
    }

    public function mount(?int $id = null): void
    {
        abort_unless($id !== null, 404); // New dispatches originate from an approved purchase order.
        parent::mount($id);
        $this->receiptDate = now()->toDateString();
        $this->loadReceiptInputs();
    }

    public function confirmDispatch(ConfirmPurchaseDispatch $action): void
    {
        $this->requireSavedDraft();
        abort_unless($this->document !== null, 422);

        $dispatch = $action->handle($this->document, $this->mutationKey('confirmDispatch'));
        $this->loadDocument($dispatch->load('lines'));
        $this->completeMutation('confirmDispatch');
        $this->loadReceiptInputs();
    }

    public function createReceipt(CreateGoodsReceiptFromPurchaseDispatch $action): mixed
    {
        abort_unless($this->document !== null, 422);

        $quantities = array_filter(
            $this->receiptQuantities,
            fn (string $quantity): bool => bccomp($quantity, '0', 3) > 0,
        );

        $locations = [];
        foreach ($this->receiptLocationIds as $lineId => $locationId) {
            if ($locationId !== null) {
                $locations[(int) $lineId] = $locationId;
            }
        }

        $receipt = $action->handle(
            $this->document, $quantities, $locations, $this->receiptDate,
            $this->mutationKey('createReceipt'),
        );
        $this->completeMutation('createReceipt');

        return $this->redirectRoute('purchases.receipts.edit', ['id' => $receipt->id], navigate: false);
    }

    private function loadReceiptInputs(): void
    {
        if (! $this->document || $this->document->status !== 'confirmed') {
            return;
        }

        $this->document->load('lines');
        $used = DB::connection('period')->table('document_relations')
            ->where('source_document_id', $this->document->id)
            ->where('relation_type', 'purchase_dispatch_to_goods_receipt')
            ->pluck('target_document_id');
        foreach ($this->document->lines as $line) {
            $accepted = $used->isEmpty() ? '0.000' : DB::connection('period')
                ->table('document_lines')->whereIn('document_id', $used)
                ->where('source_line_id', $line->source_line_id)
                ->sum('quantity');
            $remaining = bcsub((string) $line->quantity, (string) $accepted, 3);
            $this->receiptQuantities[(int) $line->id] = bccomp($remaining, '0', 3) > 0 ? $remaining : '0.000';
            $this->receiptLocationIds[(int) $line->id] ??= $line->location_id;
        }
    }
}
