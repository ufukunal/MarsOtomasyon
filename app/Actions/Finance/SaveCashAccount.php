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

        $name = trim((string) $data['name']);

        if ($code === '' || $name === '' || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException('Kasa kodu, adı ve 3 harfli para birimi zorunludur.');
        }

        if ($account
            && $account->currency !== $currency
            && $account->movements()->exists()) {
            throw new DomainException('Hareket görmüş kasanın para birimi değiştirilemez.');
        }

        $attributes = [
            'code' => $code,
            'name' => $name,
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
