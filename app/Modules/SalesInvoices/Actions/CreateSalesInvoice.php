<?php

namespace App\Modules\SalesInvoices\Actions;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Numbering\DocumentNumberIssuer;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class CreateSalesInvoice
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private DocumentNumberIssuer $numbers,
        private ResolveSalesInvoiceSource $sources,
    ) {}

    public function handle(SalesInvoiceDraftData $data, string $seriesCode = 'default'): SalesInvoice
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $seriesCode = mb_strtolower(trim($seriesCode));
        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $seriesCode) !== 1 || strlen($seriesCode) > 64) {
            throw ValidationException::withMessages([
                'series_code' => 'Fatura numara serisi canonical ve en fazla 64 karakter olmalıdır.',
            ]);
        }
        if ($data->lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Satış faturası en az bir satır içermelidir.',
            ]);
        }

        try {
            return DB::transaction(function () use ($companyId, $data, $seriesCode): SalesInvoice {
                $source = $this->sources->resolve($companyId, $data);
                $taxIdentityType = $source->account->getRawOriginal('tax_identity_type');
                if (! is_string($taxIdentityType)) {
                    throw new LogicException('Cari vergi kimlik tipi snapshot değeri geçersiz.');
                }

                $number = $this->numbers->issue($companyId, DocumentType::SalesInvoice, $seriesCode);
                $invoice = SalesInvoice::query()->create([
                    'company_id' => $companyId,
                    'account_id' => $source->account->getKey(),
                    'source_billing_address_id' => $source->billingAddress->getKey(),
                    'source_sales_order_id' => $source->salesOrder?->getKey(),
                    'source_dispatch_id' => $source->dispatch?->getKey(),
                    'number' => $number->number,
                    'series_code' => $number->seriesCode,
                    'sequence_value' => $number->sequenceValue,
                    'mode' => $data->mode,
                    'status' => SalesInvoiceStatus::Draft,
                    'invoice_date' => $data->invoiceDate,
                    'currency_code' => $source->currencyCode,
                    'customer_legal_name' => $source->account->legal_name,
                    'customer_trade_name' => $source->account->trade_name,
                    'customer_tax_identity_type' => $taxIdentityType,
                    'customer_tax_number' => $source->account->tax_number,
                    'customer_tax_office' => $source->account->tax_office,
                    'recipient_name' => $source->billingAddress->recipient_name,
                    'address_line1' => $source->billingAddress->line1,
                    'address_line2' => $source->billingAddress->line2,
                    'district' => $source->billingAddress->district,
                    'city' => $source->billingAddress->city,
                    'postal_code' => $source->billingAddress->postal_code,
                    'country_code' => strtoupper((string) $source->billingAddress->country_code),
                    'note' => $this->nullableTrimmed($data->note),
                ]);

                foreach ($source->lines as $index => $line) {
                    $invoice->lines()->create([
                        'company_id' => $companyId,
                        'source_sales_order_id' => $line->sourceSalesOrderId,
                        'source_sales_order_line_id' => $line->sourceSalesOrderLineId,
                        'source_dispatch_id' => $line->sourceDispatchId,
                        'source_dispatch_line_id' => $line->sourceDispatchLineId,
                        'position' => $index + 1,
                        'product_id' => $line->productId,
                        'warehouse_id' => $line->warehouseId,
                        'location_id' => $line->locationId,
                        'product_code' => $line->productCode,
                        'product_name' => $line->productName,
                        'description' => $line->description,
                        'quantity' => $line->quantity,
                    ]);
                }

                return $invoice->load('lines');
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'series_code' => $exception->getMessage(),
            ]);
        }
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
