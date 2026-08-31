<?php

namespace App\Modules\B2B\Portal;

use App\Modules\Accounts\Ledger\AccountLedgerReader;
use App\Modules\B2B\Enums\B2BPermission;
use Illuminate\Http\Request;
use Illuminate\View\View;

final readonly class B2BStatementController
{
    public function __construct(private B2BPortalAccess $access, private AccountLedgerReader $ledger) {}

    public function __invoke(Request $request): View
    {
        $this->access->authorize(B2BPermission::ViewStatement);
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = isset($validated['from']) ? (string) $validated['from'] : null;
        $to = isset($validated['to']) ? (string) $validated['to'] : null;
        $account = $this->access->account();

        return view('b2b.statement', [
            'account' => $account,
            'statement' => $this->ledger->statement($account, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }
}
