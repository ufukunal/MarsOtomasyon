<?php

namespace App\Modules\Accounts\Ledger;

enum AccountBalanceState: string
{
    case Debtor = 'debtor';
    case Creditor = 'creditor';
    case Zero = 'zero';

    public function label(): string
    {
        return match ($this) {
            self::Debtor => 'Borçlu',
            self::Creditor => 'Alacaklı',
            self::Zero => 'Bakiye Yok',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Debtor => 'balance-debtor',
            self::Creditor => 'balance-creditor',
            self::Zero => 'balance-zero',
        };
    }
}
