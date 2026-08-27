<?php

namespace App\Modules\PurchaseReturns\Enums;

enum PurchaseReturnStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Finalized => 'Kesinleşti',
        };
    }
}
