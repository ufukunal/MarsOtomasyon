<?php

namespace App\Console\Commands;

use App\Support\Integrity\Checks\ReservationBalanceCheck;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\RunIntegrityCheckCommand;

class IntegrityReservationsCommand extends RunIntegrityCheckCommand
{
    protected $signature = 'integrity:reservations';

    protected $description = 'Aktif rezervasyonlar ile rezerve stok özetini doğrular';

    /** @return class-string<IntegrityCheck> */
    protected function checkClass(): string
    {
        return ReservationBalanceCheck::class;
    }
}
