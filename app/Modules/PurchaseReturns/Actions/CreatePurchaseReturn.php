<?php

namespace App\Modules\PurchaseReturns\Actions;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Numbering\DocumentNumberIssuer;
use App\Modules\PurchaseReturns\Enums\PurchaseReturnStatus;
use App\Modules\PurchaseReturns\Models\PurchaseReturn;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreatePurchaseReturn
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private PurchaseReturnDraftResolver $resolver,
        private DocumentNumberIssuer $numbers,
    ) {}

    public function handle(PurchaseReturnDraftData $data, string $seriesCode = 'default'): PurchaseReturn
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $seriesCode = mb_strtolower(trim($seriesCode));
        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $seriesCode) !== 1 || strlen($seriesCode) > 64) {
            throw ValidationException::withMessages(['series_code' => 'Satınalma iadesi numara serisi canonical ve en fazla 64 karakter olmalıdır.']);
        }

        $draft = $this->resolver->resolve($companyId, $data);

        try {
            return DB::transaction(function () use ($companyId, $seriesCode, $draft): PurchaseReturn {
                $number = $this->numbers->issue($companyId, DocumentType::PurchaseReturn, $seriesCode);
                $calculation = $draft->calculation;
                $purchaseReturn = PurchaseReturn::query()->create([
                    'company_id' => $companyId,
                    'purchase_order_id' => $draft->purchaseOrderId,
                    'account_id' => $draft->accountId,
                    'number' => $number->number,
                    'series_code' => $number->seriesCode,
                    'sequence_value' => $number->sequenceValue,
                    'status' => PurchaseReturnStatus::Draft,
                    'finalized_at' => null,
                    'return_date' => $draft->returnDate,
                    'currency_code' => $draft->currencyCode,
                    'document_discount_rate' => $draft->documentDiscountRate,
                    'base_net_total' => $calculation->baseNet,
                    'line_discount_total' => $calculation->lineDiscountNet,
                    'document_discount_total' => $calculation->documentDiscountNet,
                    'net_total' => $calculation->net,
                    'tax_total' => $calculation->tax,
                    'gross_total' => $calculation->gross,
                    'note' => $draft->note,
                ]);

                foreach ($draft->lines as $line) {
                    $result = $line->calculation;
                    $purchaseReturn->lines()->create([
                        'company_id' => $companyId,
                        'purchase_order_id' => $draft->purchaseOrderId,
                        'purchase_order_line_id' => $line->purchaseOrderLineId,
                        'goods_receipt_id' => $line->goodsReceiptId,
                        'goods_receipt_line_id' => $line->goodsReceiptLineId,
                        'supplier_invoice_id' => $line->supplierInvoiceId,
                        'supplier_invoice_line_id' => $line->supplierInvoiceLineId,
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

                return $purchaseReturn->load('lines');
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['series_code' => $exception->getMessage()]);
        }
    }
}
