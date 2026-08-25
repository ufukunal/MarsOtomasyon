<?php

namespace App\Modules\Inventory\Enums;

enum StockReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Consumed = 'consumed';

    public function isTerminal(): bool
    {
        return $this !== self::Active;
    }
}
