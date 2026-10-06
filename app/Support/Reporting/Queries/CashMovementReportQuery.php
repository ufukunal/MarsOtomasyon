<?php

namespace App\Support\Reporting\Queries;

final class CashMovementReportQuery extends AbstractFinanceMovementReportQuery
{
    protected function key(): string
    {
        return 'finance.cash_movements';
    }

    protected function title(): string
    {
        return 'Kasa Hareketleri';
    }

    protected function permission(): string
    {
        return 'finance_movements.view';
    }

    protected function accountPermission(): string
    {
        return 'cash_accounts.view';
    }

    protected function target(): string
    {
        return 'cash_accounts';
    }

    protected function movementTable(): string
    {
        return 'cash_movements';
    }

    protected function accountTable(): string
    {
        return 'cash_accounts';
    }

    protected function accountForeignKey(): string
    {
        return 'cash_account_id';
    }

    protected function accountNameExpression(): string
    {
        return 'a.name';
    }
}
