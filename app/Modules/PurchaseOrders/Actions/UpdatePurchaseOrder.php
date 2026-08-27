<?php

namespace App\Modules\PurchaseOrders\Actions;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdatePurchaseOrder
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private PurchaseOrderDraftResolver $resolver,
    ) {}

    public function handle(int $purchaseOrderId, PurchaseOrderDraftData $data): PurchaseOrder
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $draft = $this->resolver->resolve($companyId, $data);

        return DB::transaction(function () use ($companyId, $purchaseOrderId, $draft): PurchaseOrder {
            $order = PurchaseOrder::query()
                ->where('company_id', $companyId)
                ->whereKey($purchaseOrderId)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw ValidationException::withMessages(['purchase_order' => 'Satınalma siparişi aktif şirkette bulunamadı.']);
            }
            if (! $order->isDraft()) {
                throw ValidationException::withMessages(['purchase_order' => 'Yalnız taslak satınalma siparişleri düzenlenebilir.']);
            }
            if ($order->progressEffects()->exists()) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Mal kabul, alış faturası veya iptal progress kaydı başlayan sipariş artık taslak düzenleme ile değiştirilemez.',
                ]);
            }

            $calculation = $draft->calculation;
            $order->fill([
                'account_id' => $draft->accountId,
                'order_date' => $draft->orderDate,
                'currency_code' => $draft->currencyCode,
                'document_discount_rate' => $draft->documentDiscountRate,
                'base_net_total' => $calculation->baseNet,
                'line_discount_total' => $calculation->lineDiscountNet,
                'document_discount_total' => $calculation->documentDiscountNet,
                'net_total' => $calculation->net,
                'tax_total' => $calculation->tax,
                'gross_total' => $calculation->gross,
                'note' => $draft->note,
            ])->save();

            $order->lines()->delete();
            foreach ($draft->lines as $line) {
                $result = $line->calculation;
                $order->lines()->create([
                    'company_id' => $companyId,
                    'logical_line_key' => $line->logicalLineKey,
                    'position' => $line->position,
                    'product_id' => $line->productId,
                    'warehouse_id' => $line->warehouseId,
                    'location_id' => $line->locationId,
                    'product_code' => $line->productCode,
                    'product_name' => $line->productName,
                    'description' => $line->description,
                    'quantity' => $result->quantity,
                    'price_basis' => $result->priceBasis,
                    'unit_price' => $result->unitPrice,
                    'line_discount_rate' => $result->lineDiscountRate,
                    'tax_id' => $line->taxId,
                    'tax_code' => $line->taxCode,
                    'tax_rate' => $result->taxRate,
                    'tax_is_zeroed' => $line->taxIsZeroed,
                    'tax_zero_reason_id' => $line->taxZeroReasonId,
                    'tax_zero_reason_code' => $result->taxZeroReasonCode,
                    'base_net' => $result->baseNet,
                    'line_discount_net' => $result->lineDiscountNet,
                    'document_discount_net' => $result->documentDiscountNet,
                    'net_total' => $result->net,
                    'tax_total' => $result->tax,
                    'gross_total' => $result->gross,
                ]);
            }

            return $order->load('lines');
        });
    }
}
