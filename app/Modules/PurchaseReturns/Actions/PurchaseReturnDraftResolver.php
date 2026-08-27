<?php

namespace App\Modules\PurchaseReturns\Actions;

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\GoodsReceipts\Enums\GoodsReceiptStatus;
use App\Modules\GoodsReceipts\Models\GoodsReceiptLine;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\Quotes\Pricing\DeterministicTaxCalculator;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\Quotes\Pricing\TaxCalculationLineInput;
use App\Modules\SupplierInvoices\Enums\SupplierInvoiceStatus;
use App\Modules\SupplierInvoices\Models\SupplierInvoiceLine;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class PurchaseReturnDraftResolver
{
    public function __construct(private DeterministicTaxCalculator $calculator) {}

    public function resolve(int $companyId, PurchaseReturnDraftData $data): ResolvedPurchaseReturnDraft
    {
        $order = PurchaseOrder::query()
            ->with('account')
            ->where('company_id', $companyId)
            ->whereKey($data->purchaseOrderId)
            ->first();
        if (! $order instanceof PurchaseOrder) {
            throw ValidationException::withMessages(['purchase_order_id' => 'Satınalma siparişi aktif şirkette bulunamadı.']);
        }

        $account = $order->account;
        if (! $account instanceof Account
            || $account->statusEnum() !== AccountStatus::Active
            || ! in_array($account->typeEnum(), [AccountType::Supplier, AccountType::Mixed], true)) {
            throw ValidationException::withMessages(['purchase_order_id' => 'Satınalma iadesi aktif tedarikçi veya karma cariye bağlı olmalıdır.']);
        }
        if ((string) $order->currency_code !== (string) $account->book_currency_code) {
            throw ValidationException::withMessages(['purchase_order_id' => 'İade para birimi cari defter para birimiyle aynı olmalıdır.']);
        }
        if ($data->lines === [] || count($data->lines) > 200) {
            throw ValidationException::withMessages(['lines' => 'Satınalma iadesi en az 1, en fazla 200 satır içermelidir.']);
        }

        $inputs = [];
        $metadata = [];
        $usedPairs = [];
        $physicalRequested = [];
        $financialRequested = [];

        foreach ($data->lines as $offset => $requestedLine) {
            $pairKey = $requestedLine->goodsReceiptLineId.':'.$requestedLine->supplierInvoiceLineId;
            if (isset($usedPairs[$pairKey])) {
                throw ValidationException::withMessages(["lines.$offset" => 'Aynı mal kabul ve alış faturası satırı eşleşmesi bir iadede yalnız bir kez kullanılabilir.']);
            }
            $usedPairs[$pairKey] = true;

            $receiptLine = GoodsReceiptLine::query()
                ->with('goodsReceipt')
                ->where('company_id', $companyId)
                ->whereKey($requestedLine->goodsReceiptLineId)
                ->first();
            $invoiceLine = SupplierInvoiceLine::query()
                ->with('supplierInvoice')
                ->where('company_id', $companyId)
                ->whereKey($requestedLine->supplierInvoiceLineId)
                ->first();

            if (! $receiptLine instanceof GoodsReceiptLine
                || $receiptLine->goodsReceipt?->statusEnum() !== GoodsReceiptStatus::Finalized) {
                throw ValidationException::withMessages(["lines.$offset.goods_receipt_line_id" => 'Fiziksel iade kaynağı kesinleşmiş bir mal kabul satırı olmalıdır.']);
            }
            if (! $invoiceLine instanceof SupplierInvoiceLine
                || $invoiceLine->supplierInvoice?->statusEnum() !== SupplierInvoiceStatus::Finalized) {
                throw ValidationException::withMessages(["lines.$offset.supplier_invoice_line_id" => 'Finansal iade kaynağı kesinleşmiş bir alış faturası satırı olmalıdır.']);
            }
            if ((int) $receiptLine->purchase_order_id !== (int) $order->getKey()
                || (int) $invoiceLine->purchase_order_id !== (int) $order->getKey()
                || (int) $receiptLine->purchase_order_line_id !== (int) $invoiceLine->purchase_order_line_id
                || (int) $receiptLine->product_id !== (int) $invoiceLine->product_id
                || (int) $invoiceLine->supplierInvoice->account_id !== (int) $order->account_id
                || (string) $invoiceLine->supplierInvoice->currency_code !== (string) $order->currency_code) {
                throw ValidationException::withMessages(["lines.$offset" => 'Mal kabul ve alış faturası lineage aynı sipariş, sipariş satırı, ürün, tedarikçi ve para birimine ait olmalıdır.']);
            }

            $quantity = $this->positiveDecimal($requestedLine->quantity, $offset);
            $physicalRequested[$receiptLine->getKey()] = $this->add(
                $physicalRequested[$receiptLine->getKey()] ?? '0.000000',
                $quantity,
            );
            $financialRequested[$invoiceLine->getKey()] = $this->add(
                $financialRequested[$invoiceLine->getKey()] ?? '0.000000',
                $quantity,
            );

            $accepted = $this->acceptedQuantity($companyId, (int) $receiptLine->getKey());
            $physicalReturned = $this->finalizedReturnedQuantity($companyId, 'goods_receipt_line_id', (int) $receiptLine->getKey());
            $financialReturned = $this->finalizedReturnedQuantity($companyId, 'supplier_invoice_line_id', (int) $invoiceLine->getKey());

            if ($this->greaterThan($physicalRequested[$receiptLine->getKey()], $this->subtract($accepted, $physicalReturned))) {
                throw ValidationException::withMessages(["lines.$offset.quantity" => 'İade miktarı kabul edilmiş ve daha önce iade edilmemiş fiziksel miktarı aşamaz.']);
            }
            if ($this->greaterThan($financialRequested[$invoiceLine->getKey()], $this->subtract((string) $invoiceLine->quantity, $financialReturned))) {
                throw ValidationException::withMessages(["lines.$offset.quantity" => 'İade miktarı finansal kaynak alış faturası satırının kalan miktarını aşamaz.']);
            }

            $rawPriceBasis = $invoiceLine->getRawOriginal('price_basis');
            $priceBasis = is_string($rawPriceBasis) ? PriceBasis::tryFrom($rawPriceBasis) : null;
            if ($priceBasis === null) {
                throw ValidationException::withMessages(["lines.$offset.supplier_invoice_line_id" => 'Kaynak alış faturası fiyat tipi geçersiz.']);
            }

            $inputs[] = new TaxCalculationLineInput(
                key: (string) ($offset + 1),
                quantity: $quantity,
                unitPrice: (string) $invoiceLine->unit_price,
                priceBasis: $priceBasis,
                taxRate: (string) $invoiceLine->tax_rate,
                lineDiscountRate: (string) $invoiceLine->line_discount_rate,
                taxZeroReasonCode: $invoiceLine->tax_zero_reason_code === null ? null : (string) $invoiceLine->tax_zero_reason_code,
            );
            $metadata[] = [$receiptLine, $invoiceLine];
        }

        try {
            $calculation = $this->calculator->calculate($inputs, (string) $order->document_discount_rate);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        $resolvedLines = [];
        foreach ($calculation->lines as $offset => $result) {
            [$receiptLine, $invoiceLine] = $metadata[$offset];
            $resolvedLines[] = new ResolvedPurchaseReturnLine(
                position: $offset + 1,
                purchaseOrderLineId: (int) $invoiceLine->purchase_order_line_id,
                goodsReceiptId: (int) $receiptLine->goods_receipt_id,
                goodsReceiptLineId: (int) $receiptLine->getKey(),
                supplierInvoiceId: (int) $invoiceLine->supplier_invoice_id,
                supplierInvoiceLineId: (int) $invoiceLine->getKey(),
                productId: (int) $invoiceLine->product_id,
                warehouseId: (int) $receiptLine->warehouse_id,
                locationId: (int) $receiptLine->location_id,
                productCode: (string) $invoiceLine->product_code,
                productName: (string) $invoiceLine->product_name,
                description: (string) $invoiceLine->description,
                taxId: (int) $invoiceLine->tax_id,
                taxCode: (string) $invoiceLine->tax_code,
                taxIsZeroed: (bool) $invoiceLine->tax_is_zeroed,
                taxZeroReasonId: $invoiceLine->tax_zero_reason_id === null ? null : (int) $invoiceLine->tax_zero_reason_id,
                calculation: $result,
            );
        }

        return new ResolvedPurchaseReturnDraft(
            purchaseOrderId: (int) $order->getKey(),
            accountId: (int) $order->account_id,
            returnDate: $this->date($data->returnDate)->format('Y-m-d'),
            currencyCode: (string) $order->currency_code,
            documentDiscountRate: $calculation->lines[0]->documentDiscountRate,
            note: $this->note($data->note),
            lines: $resolvedLines,
            calculation: $calculation,
        );
    }

    private function acceptedQuantity(int $companyId, int $goodsReceiptLineId): string
    {
        $row = DB::table('goods_receipt_line_quality')
            ->where('company_id', $companyId)
            ->where('goods_receipt_line_id', $goodsReceiptLineId)
            ->first();

        return $row === null ? '0.000000' : (string) $row->accepted_quantity;
    }

    private function finalizedReturnedQuantity(int $companyId, string $column, int $sourceId): string
    {
        $row = DB::table('purchase_return_lines as line')
            ->join('purchase_returns as purchase_return', function ($join): void {
                $join->on('purchase_return.company_id', '=', 'line.company_id')
                    ->on('purchase_return.id', '=', 'line.purchase_return_id');
            })
            ->where('line.company_id', $companyId)
            ->where('line.'.$column, $sourceId)
            ->where('purchase_return.status', 'finalized')
            ->selectRaw('COALESCE(SUM(line.quantity), 0)::numeric(20,6)::text AS quantity')
            ->first();

        return $row === null ? '0.000000' : (string) $row->quantity;
    }

    private function positiveDecimal(string $raw, int $offset): string
    {
        $value = trim($raw);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages(["lines.$offset.quantity" => 'İade miktarı pozitif ve en fazla 6 ondalıklı olmalıdır.']);
        }
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) > 0 AS valid', [$value, $value]);
        if ($row === null || $row->valid !== true) {
            throw ValidationException::withMessages(["lines.$offset.quantity" => 'İade miktarı sıfırdan büyük olmalıdır.']);
        }

        return (string) $row->value;
    }

    private function add(string $left, string $right): string
    {
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) + CAST(? AS numeric) AS numeric(20,6))::text AS value', [$left, $right]);

        return (string) $row?->value;
    }

    private function subtract(string $left, string $right): string
    {
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) - CAST(? AS numeric) AS numeric(20,6))::text AS value', [$left, $right]);

        return (string) $row?->value;
    }

    private function greaterThan(string $left, string $right): bool
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) > CAST(? AS numeric) AS value', [$left, $right]);

        return $row?->value === true;
    }

    private function date(string $raw): DateTimeImmutable
    {
        $value = trim($raw);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages(['return_date' => 'İade tarihi YYYY-AA-GG formatında geçerli olmalıdır.']);
        }

        return $date;
    }

    private function note(?string $raw): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > 5000) {
            throw ValidationException::withMessages(['note' => 'İade notu 5000 karakteri aşamaz.']);
        }

        return $value;
    }
}
