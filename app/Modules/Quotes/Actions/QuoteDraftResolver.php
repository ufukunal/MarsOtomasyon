<?php

namespace App\Modules\Quotes\Actions;

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Products\Models\Product;
use App\Modules\Quotes\Pricing\DeterministicTaxCalculator;
use App\Modules\Quotes\Pricing\TaxCalculationLineInput;
use DateTimeImmutable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class QuoteDraftResolver
{
    public function __construct(private DeterministicTaxCalculator $calculator) {}

    public function resolve(int $companyId, QuoteDraftData $data): ResolvedQuoteDraft
    {
        $this->assertAccount($companyId, $data->accountId);
        $quoteDate = $this->date($data->quoteDate, 'quote_date', false);
        if ($quoteDate === null) {
            throw ValidationException::withMessages(['quote_date' => 'Geçerli bir teklif tarihi zorunludur.']);
        }
        $validUntil = $this->date($data->validUntil, 'valid_until', true);
        if ($validUntil !== null && $validUntil < $quoteDate) {
            throw ValidationException::withMessages(['valid_until' => 'Geçerlilik tarihi teklif tarihinden önce olamaz.']);
        }

        $currencyCode = mb_strtoupper(trim($data->currencyCode));
        if (preg_match('/^[A-Z]{3}$/D', $currencyCode) !== 1
            || ! Currency::query()->whereKey($currencyCode)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['currency_code' => 'Geçerli ve aktif bir para birimi seçilmelidir.']);
        }

        if ($data->lines === [] || count($data->lines) > 200) {
            throw ValidationException::withMessages(['lines' => 'Teklif en az 1, en fazla 200 satır içermelidir.']);
        }

        $inputs = [];
        $metadata = [];
        foreach ($data->lines as $offset => $line) {
            $position = $offset + 1;
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

            $taxRate = (string) $tax->rate;
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
            $metadata[] = [$product, $tax, $zeroReason, $description];
        }

        try {
            $calculation = $this->calculator->calculate($inputs, $data->documentDiscountRate);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        $resolvedLines = [];
        foreach ($calculation->lines as $offset => $lineResult) {
            [$product, $tax, $zeroReason, $description] = $metadata[$offset];
            $resolvedLines[] = new ResolvedQuoteLine(
                position: $offset + 1,
                productId: (int) $product->getKey(),
                productCode: (string) $product->code,
                description: $description,
                taxId: (int) $tax->getKey(),
                taxZeroReasonId: $zeroReason === null ? null : (int) $zeroReason->getKey(),
                calculation: $lineResult,
            );
        }

        return new ResolvedQuoteDraft(
            accountId: $data->accountId,
            quoteDate: $quoteDate->format('Y-m-d'),
            validUntil: $validUntil?->format('Y-m-d'),
            currencyCode: $currencyCode,
            documentDiscountRate: $calculation->lines[0]->documentDiscountRate,
            note: $this->note($data->note),
            lines: $resolvedLines,
            calculation: $calculation,
        );
    }

    private function assertAccount(int $companyId, int $accountId): void
    {
        $account = Account::query()->where('company_id', $companyId)->whereKey($accountId)->first();
        if ($account === null
            || $account->statusEnum() !== AccountStatus::Active
            || ! in_array($account->typeEnum(), [AccountType::Customer, AccountType::Mixed], true)) {
            throw ValidationException::withMessages(['account_id' => 'Aktif şirkete ait müşteri veya karma cari seçilmelidir.']);
        }
    }

    private function date(?string $raw, string $field, bool $nullable): ?DateTimeImmutable
    {
        $value = trim((string) $raw);
        if ($value === '' && $nullable) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Tarih YYYY-AA-GG formatında geçerli bir tarih olmalıdır.']);
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
            throw ValidationException::withMessages(['note' => 'Teklif notu 5000 karakteri aşamaz.']);
        }

        return $value;
    }

    private function isZeroRate(string $rate): bool
    {
        return preg_match('/^0+(?:\.0+)?$/D', trim($rate)) === 1;
    }
}
