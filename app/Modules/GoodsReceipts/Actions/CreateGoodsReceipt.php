<?php

namespace App\Modules\GoodsReceipts\Actions;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Numbering\DocumentNumberIssuer;
use App\Modules\GoodsReceipts\Enums\GoodsReceiptStatus;
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateGoodsReceipt
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private GoodsReceiptDraftResolver $resolver,
        private DocumentNumberIssuer $numbers,
    ) {}

    public function handle(GoodsReceiptDraftData $data, string $seriesCode = 'default'): GoodsReceipt
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $seriesCode = mb_strtolower(trim($seriesCode));
        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $seriesCode) !== 1 || strlen($seriesCode) > 64) {
            throw ValidationException::withMessages([
                'series_code' => 'Mal kabul numara serisi canonical ve en fazla 64 karakter olmalıdır.',
            ]);
        }

        try {
            return DB::transaction(function () use ($companyId, $data, $seriesCode): GoodsReceipt {
                $draft = $this->resolver->resolve($companyId, $data);
                $number = $this->numbers->issue($companyId, DocumentType::GoodsReceipt, $seriesCode);

                $receipt = GoodsReceipt::query()->create([
                    'company_id' => $companyId,
                    'purchase_order_id' => $draft->purchaseOrderId,
                    'account_id' => $draft->accountId,
                    'number' => $number->number,
                    'series_code' => $number->seriesCode,
                    'sequence_value' => $number->sequenceValue,
                    'status' => GoodsReceiptStatus::Draft,
                    'receipt_date' => $draft->receiptDate,
                    'note' => $draft->note,
                    'finalized_at' => null,
                ]);

                $this->persistLines($receipt, $draft);

                return $receipt->load('lines');
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['series_code' => $exception->getMessage()]);
        }
    }

    private function persistLines(GoodsReceipt $receipt, ResolvedGoodsReceiptDraft $draft): void
    {
        foreach ($draft->lines as $position => $line) {
            $receipt->lines()->create([
                'company_id' => $receipt->company_id,
                'purchase_order_id' => $draft->purchaseOrderId,
                'purchase_order_line_id' => $line->purchaseOrderLineId,
                'position' => $position + 1,
                'product_id' => $line->productId,
                'warehouse_id' => $line->warehouseId,
                'location_id' => $line->locationId,
                'product_code' => $line->productCode,
                'product_name' => $line->productName,
                'received_quantity' => $line->receivedQuantity,
                'accepted_quantity' => $line->acceptedQuantity,
                'pending_quantity' => $line->pendingQuantity,
                'rejected_quantity' => $line->rejectedQuantity,
                'provisional_unit_cost' => $line->provisionalUnitCost,
                'note' => $line->note,
            ]);
        }
    }
}
