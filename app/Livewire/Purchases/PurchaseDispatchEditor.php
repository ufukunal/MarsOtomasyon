<?php

namespace App\Livewire\Purchases;

use App\Actions\Purchases\ConfirmPurchaseDispatch;
use App\Actions\Purchases\CreateGoodsReceiptFromPurchaseDispatch;
use App\Actions\Purchases\ReopenPurchaseDispatchForEditing;
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
        return ['confirmDispatch', 'createReceipt', 'reopenEdit'];
    }

    public function mount(?int $id = null): void
    {
        abort_unless($id !== null, 404); // New dispatches originate from an approved purchase order.
        parent::mount($id);
        $this->receiptDate = now()->toDateString();
        $this->loadReceiptInputs();
    }

    public function canReopenForEditing(): bool
    {
        return $this->document !== null
            && auth()->user()?->can('purchase_dispatches.view')
            && auth()->user()?->can('purchase_dispatches.update')
            && app(ReopenPurchaseDispatchForEditing::class)->eligible($this->document);
    }

    public function reopenForEditing(ReopenPurchaseDispatchForEditing $action): void
    {
        abort_unless($this->document !== null && $this->canReopenForEditing(), 403);

        $this->loadDocument($action->handle($this->document, $this->mutationKey('reopenEdit')));
        $this->completeMutation('reopenEdit');
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
        $this->requireSavedDraft();
        abort_unless($this->document !== null, 422);

        $this->validate([
            'receiptDate' => ['required', 'date'],
            'receiptQuantities' => ['required', 'array'],
            'receiptQuantities.*' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'receiptLocationIds.*' => ['nullable', 'integer'],
        ]);

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
