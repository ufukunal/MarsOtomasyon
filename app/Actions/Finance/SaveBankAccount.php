<?php

namespace App\Actions\Finance;

use App\Models\Period\BankAccount;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use DomainException;

final class SaveBankAccount
{
    /** @param array<string,mixed> $data */
    public function handle(array $data, ?BankAccount $account = null, ?int $expectedVersion = null): BankAccount
    {
        MutationAuthorizer::authorize($account ? 'bank_accounts.update' : 'bank_accounts.create');
        PeriodContext::ensureWritable();

        $code = strtoupper(trim((string) $data['code']));
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'TRY')));

        $bankName = trim((string) $data['bank_name']);
        $accountName = trim((string) $data['account_name']);
        $iban = strtoupper(preg_replace('/\s+/', '', trim((string) ($data['iban'] ?? ''))) ?? '');

        if ($code === ''
            || $bankName === ''
            || $accountName === ''
            || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException('Banka hesap kodu, banka adı, hesap adı ve para birimi zorunludur.');
        }

        if ($iban !== '' && ! preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/', $iban)) {
            throw new DomainException('IBAN biçimi geçersiz.');
        }

        if ($account
            && $account->currency !== $currency
            && $account->movements()->exists()) {
            throw new DomainException('Hareket görmüş banka hesabının para birimi değiştirilemez.');
        }

        $attributes = [
            'code' => $code,
            'bank_name' => $bankName,
            'account_name' => $accountName,
            'iban' => $iban !== '' ? $iban : null,
            'currency' => $currency,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($account) {
            return $account->updateWithVersion(
                $attributes,
                $expectedVersion ?? (int) $account->version,
            );
        }

        return BankAccount::query()->create($attributes);
    }
}
