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

        if ($code === '' || strlen($currency) !== 3) {
            throw new DomainException('Finans hesabı kodu ve 3 harfli para birimi zorunludur.');
        }

        $attributes = [
            'code' => $code,
            'bank_name' => trim((string) $data['bank_name']),
            'account_name' => trim((string) $data['account_name']),
            'iban' => trim((string) ($data['iban'] ?? '')) ?: null,
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
