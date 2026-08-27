<?php

namespace App\Modules\GoodsReceipts\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\GoodsReceipts\Enums\GoodsReceiptStatus;
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use App\Modules\GoodsReceipts\Models\GoodsReceiptLine;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderProgressType;
use App\Modules\PurchaseOrders\Progress\PurchaseOrderProgressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class FinalizeGoodsReceipt
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private StockMovementPoster $stockMovements,
        private PurchaseOrderProgressService $progress,
        private Clock $clock,
    ) {}

    public function handle(int $goodsReceiptId): GoodsReceipt
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return DB::transaction(function () use ($companyId, $goodsReceiptId): GoodsReceipt {
            $receipt = GoodsReceipt::query()
                ->where('company_id', $companyId)
                ->whereKey($goodsReceiptId)
                ->lockForUpdate()
                ->first();

            if (! $receipt instanceof GoodsReceipt) {
                throw ValidationException::withMessages(['goods_receipt' => 'Mal kabul aktif şirkette bulunamadı.']);
            }
            if ($receipt->statusEnum() === GoodsReceiptStatus::Finalized) {
                return $receipt;
            }

            $lines = $receipt->lines()->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Mal kabul kesinleştirmek için en az bir satır içermelidir.']);
            }

            /** @var GoodsReceiptLine $line */
            foreach ($lines as $line) {
                if ((string) $line->accepted_quantity === '0.000000') {
                    continue;
                }

                $this->stockMovements->post(new PostStockMovementData(
                    sourceEffect: new SourceEffectIdentity(
                        $companyId,
                        'goods_receipt_line',
                        (string) $line->getKey(),
                        'stock.in',
                    ),
                    productId: (int) $line->product_id,
                    warehouseId: (int) $line->warehouse_id,
                    locationId: (int) $line->location_id,
                    movementType: StockMovementType::GoodsReceiptIn,
                    quantity: (string) $line->accepted_quantity,
                    unitCost: (string) $line->provisional_unit_cost,
                    note: 'Mal kabul '.$receipt->number,
                ));

                $this->progress->record(
                    new SourceEffectIdentity(
                        $companyId,
                        'goods_receipt_line',
                        (string) $line->getKey(),
                        'progress.receive',
                    ),
                    (int) $line->purchase_order_line_id,
                    PurchaseOrderProgressType::Received,
                    (string) $line->accepted_quantity,
                );
            }

            $receipt->forceFill([
                'status' => GoodsReceiptStatus::Finalized,
                'finalized_at' => $this->clock->now(),
            ])->save();

            return $receipt->refresh();
        });
    }
}
