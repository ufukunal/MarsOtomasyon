<?php

namespace App\Modules\B2B\Portal;

use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountB2BPolicy;
use App\Modules\B2B\Enums\B2BPermission;
use App\Modules\B2B\Models\B2BUser;
use Illuminate\Support\Facades\Auth;

final class B2BPortalAccess
{
    public function user(): B2BUser
    {
        $user = Auth::guard('b2b')->user();
        abort_unless($user instanceof B2BUser, 401);

        return $user;
    }

    public function account(): Account
    {
        $user = $this->user();
        $account = Account::query()
            ->where('company_id', $user->company_id)
            ->whereKey($user->account_id)
            ->first();
        abort_unless($account instanceof Account, 404);

        return $account;
    }

    public function policy(): AccountB2BPolicy
    {
        $user = $this->user();
        $policy = AccountB2BPolicy::query()
            ->where('company_id', $user->company_id)
            ->where('account_id', $user->account_id)
            ->where('is_enabled', true)
            ->first();
        abort_unless($policy instanceof AccountB2BPolicy, 403);

        return $policy;
    }

    public function can(B2BPermission $permission): bool
    {
        return $this->user()->hasPermission($permission) && $this->policy()->allows($permission);
    }

    public function authorize(B2BPermission $permission): void
    {
        abort_unless($this->can($permission), 403);
    }
}
