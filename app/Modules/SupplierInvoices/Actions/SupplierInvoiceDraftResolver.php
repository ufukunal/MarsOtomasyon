<?php

namespace App\Modules\SupplierInvoices\Actions;

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLine;
use App\Modules\Quotes\Pricing\DeterministicTaxCalculator;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\Quotes\Pricing\TaxCalculationLineInput;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class SupplierInvoiceDraftResolver
{
    public function __construct(private DeterministicTaxCalculator $calculator) {}

    public function resolve(int $companyId, SupplierInvoiceDraftData $data): ResolvedSupplierInvoiceDraft
    {
        $order = PurchaseOrder::query()
            ->with(['account', 'lines.progress'])
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
            throw ValidationException::withMessages(['purchase_order_id' => 'Kaynak satınalma siparişi aktif tedarikçi veya karma cariye bağlı olmalıdır.']);
        }

        if ((string) $order->currency_code !== (string) $account->book_currency_code) {
            throw ValidationException::withMessages(['purchase_order_id' => 'Alış faturası cari ledger posting için sipariş para birimi cari defter para birimiyle aynı olmalıdır.']);
        }

        if ($data->lines === [] || count($data->lines) > 200) {
            throw ValidationException::withMessages(['lines' => 'Alış faturası en az 1, en fazla 200 satır içermelidir.']);
        }

        $sourceLines = $order->lines->keyBy(fn (PurchaseOrderLine $line): int => (int) $line->getKey());
        $inputs = [];
        $metadata = [];
        $used = [];

        foreach ($data->lines as $offset => $requestedLine) {
            if (isset($used[$requestedLine->purchaseOrderLineId])) {
                throw ValidationException::withMessages(["lines.$offset.purchase_order_line_id" => 'Aynı satınalma siparişi satırı faturada birden fazla kez kullanılamaz.']);
            }
            $used[$requestedLine->purchaseOrderLineId] = true;

            $source = $sourceLines->get($requestedLine->purchaseOrderLineId);
            if (! $source instanceof PurchaseOrderLine) {
                throw ValidationException::withMessages(["lines.$offset.purchase_order_line_id" => 'Fatura satırı kaynak satınalma siparişine ait olmalıdır.']);
            }

            $quantity = $this->positiveDecimal($requestedLine->quantity, $offset);
            $progress = DB::table('purchase_order_line_progress')
                ->where('company_id', $companyId)
                ->where('purchase_order_line_id', $source->getKey())
                ->first();
            $remaining = $progress === null ? (string) $source->quantity : (string) $progress->invoice_remaining_quantity;
            if ($this->greaterThan($quantity, $remaining)) {
                throw ValidationException::withMessages(["lines.$offset.quantity" => 'Fatura miktarı satınalma siparişi kalan faturalama miktarını aşamaz.']);
            }

            $priceBasis = $source->price_basis;
            if (! $priceBasis instanceof PriceBasis) {
                throw ValidationException::withMessages(["lines.$offset.purchase_order_line_id" => 'Kaynak satınalma satırı fiyat tipi geçersiz.']);
            }

            $inputs[] = new TaxCalculationLineInput(
                key: (string) ($offset + 1),
                quantity: $quantity,
                unitPrice: (string) $source->unit_price,
                priceBasis: $priceBasis,
                taxRate: (string) $source->tax_rate,
                lineDiscountRate: (string) $source->line_discount_rate,
                taxZeroReasonCode: $source->tax_zero_reason_code === null ? null : (string) $source->tax_zero_reason_code,
            );
            $metadata[] = $source;
        }

        try {
            $calculation = $this->calculator->calculate($inputs, (string) $order->document_discount_rate);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        $resolvedLines = [];
        foreach ($calculation->lines as $offset => $result) {
            $source = $metadata[$offset];
            $resolvedLines[] = new ResolvedSupplierInvoiceLine(
                position: $offset + 1,
                purchaseOrderLineId: (int) $source->getKey(),
                productId: (int) $source->product_id,
                productCode: (string) $source->product_code,
                productName: (string) $source->product_name,
                description: (string) $source->description,
                taxId: (int) $source->tax_id,
                taxCode: (string) $source->tax_code,
                taxIsZeroed: (bool) $source->tax_is_zeroed,
                taxZeroReasonId: $source->tax_zero_reason_id === null ? null : (int) $source->tax_zero_reason_id,
                calculation: $result,
            );
        }

        return new ResolvedSupplierInvoiceDraft(
            purchaseOrderId: (int) $order->getKey(),
            accountId: (int) $order->account_id,
            invoiceDate: $this->date($data->invoiceDate)->format('Y-m-d'),
            currencyCode: (string) $order->currency_code,
            documentDiscountRate: $calculation->lines[0]->documentDiscountRate,
            note: $this->note($data->note),
            lines: $resolvedLines,
            calculation: $calculation,
        );
    }

    private function positiveDecimal(string $raw, int $offset): string
    {
        $value = trim($raw);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages(["lines.$offset.quantity" => 'Fatura miktarı pozitif ve en fazla 6 ondalıklı olmalıdır.']);
        }

        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) > 0 AS valid', [$value, $value]);
        if ($row === null || $row->valid !== true) {
            throw ValidationException::withMessages(["lines.$offset.quantity" => 'Fatura miktarı sıfırdan büyük olmalıdır.']);
        }

        return (string) $row->value;
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
            throw ValidationException::withMessages(['invoice_date' => 'Fatura tarihi YYYY-AA-GG formatında geçerli olmalıdır.']);
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
            throw ValidationException::withMessages(['note' => 'Fatura notu 5000 karakteri aşamaz.']);
        }

        return $value;
    }
}
