<?php

namespace App\Modules\PurchaseOrders\Actions;

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use App\Modules\Quotes\Pricing\DeterministicTaxCalculator;
use App\Modules\Quotes\Pricing\TaxCalculationLineInput;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class PurchaseOrderDraftResolver
{
    public function __construct(private DeterministicTaxCalculator $calculator) {}

    public function resolve(int $companyId, PurchaseOrderDraftData $data): ResolvedPurchaseOrderDraft
    {
        $this->assertSupplier($companyId, $data->accountId);
        $orderDate = $this->date($data->orderDate);

        $currencyCode = mb_strtoupper(trim($data->currencyCode));
        if (preg_match('/^[A-Z]{3}$/D', $currencyCode) !== 1
            || ! Currency::query()->whereKey($currencyCode)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['currency_code' => 'Geçerli ve aktif bir para birimi seçilmelidir.']);
        }

        if ($data->lines === [] || count($data->lines) > 200) {
            throw ValidationException::withMessages(['lines' => 'Satınalma siparişi en az 1, en fazla 200 satır içermelidir.']);
        }

        $inputs = [];
        $metadata = [];
        $logicalKeys = [];
        foreach ($data->lines as $offset => $line) {
            $position = $offset + 1;
            $logicalLineKey = $this->logicalLineKey($line->logicalLineKey, $offset);
            if (isset($logicalKeys[$logicalLineKey])) {
                throw ValidationException::withMessages(["lines.$offset.logical_line_key" => 'Aynı satınalma satırı kimliği birden fazla kez kullanılamaz.']);
            }
            $logicalKeys[$logicalLineKey] = true;

            [$warehouseId, $locationId] = $this->allocation($companyId, $line, $offset);
            $product = Product::query()
                ->with('tax')
                ->where('company_id', $companyId)
                ->whereKey($line->productId)
                ->where('status', 'active')
                ->first();
            $tax = $product?->tax;

            if ($product === null || $tax === null || ! (bool) $tax->is_active) {
                throw ValidationException::withMessages(["lines.$offset.product_id" => 'Aktif şirkete ait, aktif vergi tanımlı bir ürün seçilmelidir.']);
            }

            $naturalTaxRate = (string) $tax->rate;
            if ($line->taxIsZeroed && $this->isZeroRate($naturalTaxRate)) {
                throw ValidationException::withMessages(["lines.$offset.tax_is_zeroed" => 'Doğal yüzde 0 KDV satırında KDV sıfırlama işareti kullanılmaz.']);
            }

            $taxRate = $line->taxIsZeroed ? '0.000000' : $naturalTaxRate;
            $zeroReason = null;
            if ($this->isZeroRate($taxRate)) {
                if ($line->taxZeroReasonId === null) {
                    throw ValidationException::withMessages(["lines.$offset.tax_zero_reason_id" => 'Yüzde 0 vergi satırında aktif KDV sıfır nedeni zorunludur.']);
                }
                $zeroReason = TaxZeroReason::query()
                    ->where('company_id', $companyId)
                    ->whereKey($line->taxZeroReasonId)
                    ->where('is_active', true)
                    ->first();
                if ($zeroReason === null) {
                    throw ValidationException::withMessages(["lines.$offset.tax_zero_reason_id" => 'Aktif şirkete ait geçerli KDV sıfır nedeni seçilmelidir.']);
                }
            } elseif ($line->taxZeroReasonId !== null) {
                throw ValidationException::withMessages(["lines.$offset.tax_zero_reason_id" => 'KDV sıfır nedeni yalnız yüzde 0 vergi satırında kullanılabilir.']);
            }

            $description = trim((string) $line->description);
            $description = $description === '' ? (string) $product->name : $description;
            if (mb_strlen($description) > 200) {
                throw ValidationException::withMessages(["lines.$offset.description" => 'Satır açıklaması 200 karakteri aşamaz.']);
            }

            $inputs[] = new TaxCalculationLineInput(
                key: (string) $position,
                quantity: $line->quantity,
                unitPrice: $line->unitPrice,
                priceBasis: $line->priceBasis,
                taxRate: $taxRate,
                lineDiscountRate: $line->lineDiscountRate,
                taxZeroReasonCode: $zeroReason === null ? null : (string) $zeroReason->code,
            );
            $metadata[] = [$logicalLineKey, $warehouseId, $locationId, $product, $tax, $line->taxIsZeroed, $zeroReason, $description];
        }

        try {
            $calculation = $this->calculator->calculate($inputs, $data->documentDiscountRate);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        $resolvedLines = [];
        foreach ($calculation->lines as $offset => $lineResult) {
            [$logicalLineKey, $warehouseId, $locationId, $product, $tax, $taxIsZeroed, $zeroReason, $description] = $metadata[$offset];
            $resolvedLines[] = new ResolvedPurchaseOrderLine(
                position: $offset + 1,
                logicalLineKey: $logicalLineKey,
                productId: (int) $product->getKey(),
                warehouseId: $warehouseId,
                locationId: $locationId,
                productCode: (string) $product->code,
                productName: (string) $product->name,
                description: $description,
                taxId: (int) $tax->getKey(),
                taxCode: (string) $tax->code,
                taxIsZeroed: $taxIsZeroed,
                taxZeroReasonId: $zeroReason === null ? null : (int) $zeroReason->getKey(),
                calculation: $lineResult,
            );
        }

        return new ResolvedPurchaseOrderDraft(
            accountId: $data->accountId,
            orderDate: $orderDate->format('Y-m-d'),
            currencyCode: $currencyCode,
            documentDiscountRate: $calculation->lines[0]->documentDiscountRate,
            note: $this->note($data->note),
            lines: $resolvedLines,
            calculation: $calculation,
        );
    }

    /** @return array{?int, ?int} */
    private function allocation(int $companyId, PurchaseOrderLineData $line, int $offset): array
    {
        if (($line->warehouseId === null) !== ($line->locationId === null)) {
            throw ValidationException::withMessages(["lines.$offset.warehouse_id" => 'Depo ve lokasyon birlikte seçilmelidir.']);
        }
        if ($line->warehouseId === null) {
            return [null, null];
        }

        $warehouse = Warehouse::query()
            ->where('company_id', $companyId)
            ->whereKey($line->warehouseId)
            ->where('is_active', true)
            ->first();
        if ($warehouse === null) {
            throw ValidationException::withMessages(["lines.$offset.warehouse_id" => 'Aktif şirkete ait aktif bir depo seçilmelidir.']);
        }

        $location = WarehouseLocation::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $line->warehouseId)
            ->whereKey($line->locationId)
            ->where('is_active', true)
            ->first();
        if ($location === null) {
            throw ValidationException::withMessages(["lines.$offset.location_id" => 'Seçilen depoya ait aktif bir lokasyon seçilmelidir.']);
        }

        return [(int) $warehouse->getKey(), (int) $location->getKey()];
    }

    private function logicalLineKey(?string $raw, int $offset): string
    {
        $value = mb_strtolower(trim((string) $raw));
        if ($value === '') {
            return (string) Str::uuid();
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value) !== 1) {
            throw ValidationException::withMessages(["lines.$offset.logical_line_key" => 'Satınalma siparişi satırı kimliği geçersiz.']);
        }

        return $value;
    }

    private function assertSupplier(int $companyId, int $accountId): void
    {
        $account = Account::query()->where('company_id', $companyId)->whereKey($accountId)->first();
        if ($account === null
            || $account->statusEnum() !== AccountStatus::Active
            || ! in_array($account->typeEnum(), [AccountType::Supplier, AccountType::Mixed], true)) {
            throw ValidationException::withMessages(['account_id' => 'Aktif şirkete ait tedarikçi veya karma cari seçilmelidir.']);
        }
    }

    private function date(string $raw): DateTimeImmutable
    {
        $value = trim($raw);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages(['order_date' => 'Tarih YYYY-AA-GG formatında geçerli bir tarih olmalıdır.']);
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
            throw ValidationException::withMessages(['note' => 'Sipariş notu 5000 karakteri aşamaz.']);
        }

        return $value;
    }

    private function isZeroRate(string $rate): bool
    {
        return preg_match('/^0+(?:\.0+)?$/D', trim($rate)) === 1;
    }
}
