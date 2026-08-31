<?php

namespace App\Modules\B2B\Portal;

use App\Modules\Accounts\Ledger\AccountLedgerReader;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\B2B\Enums\B2BPermission;
use Illuminate\View\View;

final readonly class B2BDashboardController
{
    public function __construct(private B2BPortalAccess $access, private AccountLedgerReader $ledger) {}

    public function __invoke(): View
    {
        $user = $this->access->user();
        $account = $this->access->account();
        $canBalance = $this->access->can(B2BPermission::ViewBalance);
        $addresses = AccountAddress::query()
            ->where('company_id', $user->company_id)
            ->where('account_id', $user->account_id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return view('b2b.dashboard', [
            'b2bUser' => $user,
            'account' => $account,
            'policy' => $this->access->policy(),
            'balance' => $canBalance ? $this->ledger->balance($account) : null,
            'addresses' => $addresses,
            'permissions' => B2BPermission::cases(),
        ]);
    }
}
