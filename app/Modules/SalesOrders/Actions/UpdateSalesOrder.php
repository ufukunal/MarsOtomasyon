<?php

namespace App\Modules\SalesOrders\Actions;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateSalesOrder
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private SalesOrderDraftResolver $resolver,
        private AuditRecorder $audit,
        private SalesOrderAuditSnapshot $auditSnapshot,
    ) {}

    public function handle(int $salesOrderId, SalesOrderDraftData $data): SalesOrder
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $draft = $this->resolver->resolve($companyId, $data);

        return DB::transaction(function () use ($companyId, $salesOrderId, $draft): SalesOrder {
            $order = SalesOrder::query()
                ->where('company_id', $companyId)
                ->whereKey($salesOrderId)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw ValidationException::withMessages(['sales_order' => 'Sipariş aktif şirkette bulunamadı.']);
            }
            if (! $order->isDraft() || ! $order->isManual()) {
                throw ValidationException::withMessages(['sales_order' => 'Yalnız manuel oluşturulmuş taslak siparişler düzenlenebilir.']);
            }

            $before = $this->auditSnapshot->capture($order);
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
                    'source_quote_revision_line_id' => null,
                    'position' => $line->position,
                    'product_id' => $line->productId,
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

            $this->audit->record(
                AuditAction::SalesOrderUpdated,
                AuditTargetType::SalesOrder,
                $order->getKey(),
                before: $before,
                after: $this->auditSnapshot->capture($order),
            );

            return $order->load('lines');
        });
    }
}
