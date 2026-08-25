<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Identity\TaxIdentity;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Models\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateAccount
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $accountId, UpdateAccountData $data): Account
    {
        $companyId = $this->companyId();
        $code = $this->normalizeCode($data->code);
        $legalName = $this->requiredText($data->legalName, 200, 'legal_name', 'Resmi ünvan zorunludur.');
        $tradeName = $this->optionalText($data->tradeName, 200, 'trade_name');
        $taxOffice = $this->optionalText($data->taxOffice, 120, 'tax_office');
        $taxNumber = $this->normalizeTaxIdentity($data);
        $currency = mb_strtoupper(trim($data->bookCurrencyCode));
        $this->assertActiveCurrency($currency);
        $this->assertDueDays($data->dueDays);
        $discountRate = $this->normalizeDiscount($data->discountRate);
        $riskLimit = $this->normalizeRiskLimit($data->riskLimit);

        try {
            return DB::transaction(function () use (
                $companyId,
                $accountId,
                $data,
                $code,
                $legalName,
                $tradeName,
                $taxNumber,
                $taxOffice,
                $currency,
                $discountRate,
                $riskLimit,
            ): Account {
                $account = Account::query()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->findOrFail($accountId);

                $this->assertCodeAvailable($companyId, $code, $accountId);
                $this->assertTaxIdentityAvailable($companyId, $taxNumber, $accountId);
                $this->assertBookCurrencyMutable($account, $currency);
                $before = $this->snapshot($account);

                $account->fill([
                    'code' => $code,
                    'type' => $data->type,
                    'status' => $data->status,
                    'legal_name' => $legalName,
                    'trade_name' => $tradeName,
                    'tax_identity_type' => $data->taxIdentityType,
                    'tax_number' => $taxNumber,
                    'tax_office' => $taxOffice,
                    'book_currency_code' => $currency,
                    'due_days' => $data->dueDays,
                    'discount_rate' => $discountRate,
                    'risk_limit' => $riskLimit,
                ]);
                $account->save();

                $this->audit->record(
                    AuditAction::AccountUpdated,
                    AuditTargetType::Account,
                    $account->getKey(),
                    before: $before,
                    after: $this->snapshot($account),
                );

                return $account;
            });
        } catch (QueryException $exception) {
            $this->throwIdentityConflict($exception);
        }
    }

    private function normalizeCode(string $raw): string
    {
        $code = mb_strtoupper(trim($raw));

        if (preg_match('/^[A-Z0-9]+(?:[._-][A-Z0-9]+)*$/', $code) !== 1 || mb_strlen($code) > 64) {
            throw ValidationException::withMessages([
                'code' => 'Cari kodu 1-64 karakter olmalı ve yalnız harf, rakam, nokta, alt çizgi veya tire içermelidir.',
            ]);
        }

        return $code;
    }

    private function normalizeTaxIdentity(UpdateAccountData $data): ?string
    {
        try {
            return TaxIdentity::normalize($data->taxIdentityType, $data->taxNumber);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['tax_number' => $exception->getMessage()]);
        }
    }

    private function assertActiveCurrency(string $currency): void
    {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1
            || ! Currency::query()->whereKey($currency)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['book_currency_code' => 'Geçerli ve aktif bir cari para birimi seçilmelidir.']);
        }
    }

    private function assertBookCurrencyMutable(Account $account, string $currency): void
    {
        if ((string) $account->book_currency_code === $currency) {
            return;
        }

        if ($account->transactions()->exists()) {
            throw ValidationException::withMessages([
                'book_currency_code' => 'Cari hareketi oluştuktan sonra cari para birimi değiştirilemez.',
            ]);
        }
    }

    private function assertDueDays(int $days): void
    {
        if ($days < 0 || $days > 3650) {
            throw ValidationException::withMessages(['due_days' => 'Vade günü 0 ile 3650 arasında olmalıdır.']);
        }
    }

    private function normalizeDiscount(string $raw): string
    {
        $value = trim($raw);
        if (preg_match('/^\d{1,3}(?:\.\d{1,6})?$/', $value) !== 1) {
            throw ValidationException::withMessages(['discount_rate' => 'Cari iskonto oranı geçersizdir.']);
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        if ((int) $whole > 100 || ((int) $whole === 100 && trim($fraction, '0') !== '')) {
            throw ValidationException::withMessages(['discount_rate' => 'Cari iskonto oranı 0 ile 100 arasında olmalıdır.']);
        }

        return $value;
    }

    private function normalizeRiskLimit(string $raw): string
    {
        $value = trim($raw);
        if (preg_match('/^\d{1,14}(?:\.\d{1,6})?$/', $value) !== 1) {
            throw ValidationException::withMessages(['risk_limit' => 'Risk limiti geçersizdir.']);
        }

        return $value;
    }

    private function requiredText(string $raw, int $max, string $field, string $message): string
    {
        $value = trim($raw);
        if ($value === '' || mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $value;
    }

    private function optionalText(?string $raw, int $max, string $field): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => 'Alan izin verilen uzunluğu aşıyor.']);
        }

        return $value;
    }

    private function assertCodeAvailable(int $companyId, string $code, int $ignoreAccountId): void
    {
        if (Account::query()
            ->where('company_id', $companyId)
            ->whereKeyNot($ignoreAccountId)
            ->whereRaw('lower(code) = ?', [mb_strtolower($code)])
            ->exists()) {
            throw ValidationException::withMessages(['code' => 'Bu cari kodu şirkette zaten kullanılıyor.']);
        }
    }

    private function assertTaxIdentityAvailable(int $companyId, ?string $number, int $ignoreAccountId): void
    {
        if ($number === null) {
            return;
        }

        if (Account::query()
            ->where('company_id', $companyId)
            ->whereKeyNot($ignoreAccountId)
            ->where('tax_number', $number)
            ->exists()) {
            throw ValidationException::withMessages(['tax_number' => 'Bu vergi kimliği şirkette başka bir caride kullanılıyor.']);
        }
    }

    private function throwIdentityConflict(QueryException $exception): never
    {
        if ((string) $exception->getCode() !== '23505') {
            throw $exception;
        }

        $message = (string) ($exception->errorInfo[2] ?? $exception->getMessage());
        if (str_contains($message, 'accounts_company_tax_identity_unique')) {
            throw ValidationException::withMessages(['tax_number' => 'Bu vergi kimliği şirkette başka bir caride kullanılıyor.']);
        }

        throw ValidationException::withMessages(['code' => 'Bu cari kodu şirkette zaten kullanılıyor.']);
    }

    /** @return array<string, int|string|bool|null> */
    private function snapshot(Account $account): array
    {
        return [
            'code' => (string) $account->code,
            'type' => $account->typeEnum()->value,
            'status' => $account->statusEnum()->value,
            'legal_name' => (string) $account->legal_name,
            'trade_name' => $account->trade_name === null ? null : (string) $account->trade_name,
            'tax_identity_type' => $account->taxIdentityTypeEnum()->value,
            'tax_number' => $account->tax_number === null ? null : (string) $account->tax_number,
            'tax_office' => $account->tax_office === null ? null : (string) $account->tax_office,
            'book_currency_code' => (string) $account->book_currency_code,
            'due_days' => (int) $account->due_days,
            'discount_rate' => (string) $account->discount_rate,
            'risk_limit' => (string) $account->risk_limit,
        ];
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();

        if (! is_int($companyId)) {
            throw new \LogicException('Account update requires a persisted active company.');
        }

        return $companyId;
    }
}
