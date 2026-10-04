<?php

namespace App\Actions\Finance;

use App\Models\Period\CashAccount;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use DomainException;

final class SaveCashAccount
{
    /** @param array<string,mixed> $data */
    public function handle(array $data, ?CashAccount $account = null, ?int $expectedVersion = null): CashAccount
    {
        MutationAuthorizer::authorize($account ? 'cash_accounts.update' : 'cash_accounts.create');
        PeriodContext::ensureWritable();

        $code = strtoupper(trim((string) $data['code']));
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'TRY')));

        if ($code === '' || strlen($currency) !== 3) {
            throw new DomainException('Finans hesabı kodu ve 3 harfli para birimi zorunludur.');
        }

        $attributes = [
            'code' => $code,
            'name' => trim((string) $data['name']),
            'currency' => $currency,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($account) {
            return $account->updateWithVersion(
                $attributes,
                $expectedVersion ?? (int) $account->version,
            );
        }

        return CashAccount::query()->create($attributes);
    }
}
