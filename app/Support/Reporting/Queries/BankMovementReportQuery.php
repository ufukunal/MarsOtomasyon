<?php

namespace App\Support\Reporting\Queries;

final class BankMovementReportQuery extends AbstractFinanceMovementReportQuery
{
    protected function key(): string { return 'finance.bank_movements'; }
    protected function title(): string { return 'Banka Hareketleri'; }
    protected function permission(): string { return 'finance_movements.view'; }
    protected function accountPermission(): string { return 'bank_accounts.view'; }
    protected function target(): string { return 'bank_accounts'; }
    protected function movementTable(): string { return 'bank_movements'; }
    protected function accountTable(): string { return 'bank_accounts'; }
    protected function accountForeignKey(): string { return 'bank_account_id'; }
    protected function accountNameExpression(): string { return 'a.account_name'; }
    protected function hasOrigin(): bool { return true; }
}
