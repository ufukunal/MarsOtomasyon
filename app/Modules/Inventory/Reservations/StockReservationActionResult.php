<?php

namespace App\Modules\Inventory\Reservations;

use App\Modules\Inventory\Models\StockReservation;

final readonly class StockReservationActionResult
{
    public function __construct(
        public StockReservation $reservation,
        public bool $replayed,
    ) {}
}
