<?php

namespace App\Modules\GoodsReceipts\Actions;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateGoodsReceipt
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private GoodsReceiptDraftResolver $resolver,
    ) {}

    public function handle(int $goodsReceiptId, GoodsReceiptDraftData $data): GoodsReceipt
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return DB::transaction(function () use ($companyId, $goodsReceiptId, $data): GoodsReceipt {
            $receipt = GoodsReceipt::query()
                ->where('company_id', $companyId)
                ->whereKey($goodsReceiptId)
                ->lockForUpdate()
                ->first();

            if (! $receipt instanceof GoodsReceipt) {
                throw ValidationException::withMessages(['goods_receipt' => 'Mal kabul aktif şirkette bulunamadı.']);
            }
            if (! $receipt->isDraft()) {
                throw ValidationException::withMessages(['status' => 'Yalnız taslak mal kabul düzenlenebilir.']);
            }
            if ((int) $receipt->purchase_order_id !== $data->purchaseOrderId) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'Mal kabul oluşturulduktan sonra kaynak satınalma siparişi değiştirilemez.',
                ]);
            }

            $draft = $this->resolver->resolve($companyId, $data);
            $receipt->forceFill([
                'receipt_date' => $draft->receiptDate,
                'note' => $draft->note,
            ])->save();

            $receipt->lines()->delete();
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

            return $receipt->refresh()->load('lines');
        });
    }
}
