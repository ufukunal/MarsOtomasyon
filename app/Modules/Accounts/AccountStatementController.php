<?php

namespace App\Modules\Accounts;

use App\Modules\Accounts\Ledger\AccountLedgerReader;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

final readonly class AccountStatementController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AccountLedgerReader $ledger,
    ) {}

    public function index(Request $request, int $account): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $accountModel = Account::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($account);
        $from = $this->nullableString($validated['from'] ?? null);
        $to = $this->nullableString($validated['to'] ?? null);

        return view('accounts.statement', [
            'account' => $accountModel,
            'statement' => $this->ledger->statement($accountModel, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('Cari ekstresi persisted aktif şirket gerektirir.');
        }

        return $companyId;
    }
}
