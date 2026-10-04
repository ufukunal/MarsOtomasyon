<?php

namespace App\Actions\Finance;

use App\Models\Period\CashAccount;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;

final class SaveCashAccount
{
    /** @param array<string,mixed> $data */
    public function handle(array $data, ?CashAccount $account = null, ?int $expectedVersion = null): CashAccount
    {
        MutationAuthorizer::authorize($account ? 'cash_accounts.update' : 'cash_accounts.create');
        PeriodContext::ensureWritable();

        $attributes = [
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => trim((string) $data['name']),
            'currency' => strtoupper((string) ($data['currency'] ?? 'TRY')),
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
