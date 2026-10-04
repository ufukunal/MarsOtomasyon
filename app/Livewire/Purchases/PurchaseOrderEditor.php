<?php

namespace App\Livewire\Purchases;

use App\Actions\Purchases\ApprovePurchaseOrder;
use App\Actions\Purchases\CreateGoodsReceiptFromOrder;
use App\Actions\Purchases\PurchaseLineAvailability;
use App\Actions\Purchases\SubmitPurchaseOrderForApproval;
use App\Enums\DocumentType;

class PurchaseOrderEditor extends BasePurchaseDocumentEditor
{
    public string $receiptDate = '';

    /** @var array<int,string> */
    public array $receiptQuantities = [];

    /** @var array<int,int|null> */
    public array $receiptLocationIds = [];

    protected function documentType(): DocumentType { return DocumentType::PurchaseOrder; }
    protected function permissionPrefix(): string { return 'purchase_orders'; }
    protected function pageTitle(): string { return $this->document ? 'Satınalma Siparişi' : 'Yeni Satınalma Siparişi'; }

    protected function extraMutationNames(): array
    {
        return ['submitApproval', 'approve', 'goodsReceipt'];
    }

    public function mount(?int $id = null): void
    {
        parent::mount($id);
        $this->receiptDate = now()->toDateString();
        $this->syncReceiptInputs();
    }

    public function submitApproval(SubmitPurchaseOrderForApproval $action): void
    {
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle(
            $this->document,
            $this->mutationKey('submitApproval'),
        )->load('lines'));
        $this->completeMutation('submitApproval');
    }

    public function approve(ApprovePurchaseOrder $action): void
    {
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle(
            $this->document,
            $this->mutationKey('approve'),
        )->load('lines'));
        $this->completeMutation('approve');
        $this->syncReceiptInputs();
    }

    public function createGoodsReceipt(CreateGoodsReceiptFromOrder $action): mixed
    {
        abort_unless($this->document !== null, 422);

        $quantities = array_filter(
            $this->receiptQuantities,
            fn (string $quantity): bool => bccomp($quantity, '0', 3) > 0,
        );
        $locations = [];

        foreach ($this->receiptLocationIds as $lineId => $locationId) {
            if ($locationId) {
                $locations[(int) $lineId] = (int) $locationId;
            }
        }

        $receipt = $action->handle(
            $this->document,
            $quantities,
            $locations,
            $this->receiptDate,
            $this->mutationKey('goodsReceipt'),
        );
        $this->completeMutation('goodsReceipt');

        return $this->redirectRoute('purchases.receipts.edit', ['id' => $receipt->id], navigate: false);
    }

    private function syncReceiptInputs(): void
    {
        if (! $this->document || $this->document->status !== 'approved') {
            return;
        }

        $this->document->load('lines');
        $availability = app(PurchaseLineAvailability::class);

        foreach ($this->document->lines as $line) {
            $this->receiptQuantities[$line->id] = $availability->orderReceiptRemaining($line);
            $this->receiptLocationIds[$line->id] ??= $line->location_id;
        }
    }
}
