<?php

namespace App\Modules\B2B\Portal;

use App\Modules\Accounts\Models\Account;
use App\Modules\B2B\Models\B2BUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class B2BDashboardController
{
    public function __invoke(): View
    {
        $user = Auth::guard('b2b')->user();
        abort_unless($user instanceof B2BUser, 401);

        $account = $user->account()->first();
        abort_unless($account instanceof Account, 404);

        return view('b2b.dashboard', [
            'b2bUser' => $user,
            'account' => $account,
        ]);
    }
}
