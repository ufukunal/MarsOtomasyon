<?php

namespace App\Modules\Accounts\Ledger;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class AccountStatement
{
    /**
     * @param  LengthAwarePaginator<int, array{posting_date:string,description:string,memo:?string,movement:AccountBalance,running_balance:AccountBalance}>  $rows
     */
    public function __construct(
        public AccountBalance $openingBalance,
        public AccountBalance $closingBalance,
        public LengthAwarePaginator $rows,
    ) {}
}
