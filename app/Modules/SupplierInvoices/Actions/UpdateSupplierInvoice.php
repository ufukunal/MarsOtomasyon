<?php

namespace App\Modules\SupplierInvoices\Actions;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\SupplierInvoices\Enums\SupplierInvoiceStatus;
use App\Modules\SupplierInvoices\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateSupplierInvoice
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private SupplierInvoiceDraftResolver $resolver,
        private CreateSupplierInvoice $creator,
    ) {}

    public function handle(int $supplierInvoiceId, SupplierInvoiceDraftData $data): SupplierInvoice
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $draft = $this->resolver->resolve($companyId, $data);

        return DB::transaction(function () use ($companyId, $supplierInvoiceId, $draft): SupplierInvoice {
            $invoice = SupplierInvoice::query()
                ->where('company_id', $companyId)
                ->whereKey($supplierInvoiceId)
                ->lockForUpdate()
                ->first();
            if (! $invoice instanceof SupplierInvoice) {
                throw ValidationException::withMessages(['supplier_invoice' => 'Alış faturası aktif şirkette bulunamadı.']);
            }
            if ($invoice->statusEnum() !== SupplierInvoiceStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Yalnız taslak alış faturası düzenlenebilir.']);
            }
            if ((int) $invoice->purchase_order_id !== $draft->purchaseOrderId) {
                throw ValidationException::withMessages(['purchase_order_id' => 'Taslak alış faturasının kaynak satınalma siparişi değiştirilemez.']);
            }

            $calculation = $draft->calculation;
            $invoice->forceFill([
                'invoice_date' => $draft->invoiceDate,
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

            $invoice->lines()->delete();
            $this->creator->persistLines($invoice, $draft);

            return $invoice->load('lines');
        });
    }
}
