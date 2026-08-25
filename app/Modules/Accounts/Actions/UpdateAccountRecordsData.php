<?php

namespace App\Modules\Accounts\Actions;

final readonly class UpdateAccountRecordsData
{
    /** @var list<array<string, mixed>> */
    public array $bankAccounts;

    /** @var list<array<string, mixed>> */
    public array $notes;

    /** @param array{bank_accounts?: list<array<string, mixed>>, notes?: list<array<string, mixed>>} $payload */
    public function __construct(array $payload)
    {
        $this->bankAccounts = $payload['bank_accounts'] ?? [];
        $this->notes = $payload['notes'] ?? [];
    }
}
