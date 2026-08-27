<?php

namespace App\Modules\PurchaseReturns\Enums;

/** Finalized purchase returns are append-only financial and stock corrections. */
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
