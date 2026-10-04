<?php

namespace App\Actions\Stock;

use App\Models\Period\StockReservation;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ConsumeReservation
{
    public function __construct(private readonly AdjustReservedBalance $adjustReserved) {}

    public function handle(int $reservationId, string $idempotencyKey): StockReservation
    {
        MutationAuthorizer::authorize('reservations.update');
        PeriodContext::ensureWritable();

        $result = IdempotencyKey::run(
            $idempotencyKey,
            "reservation.consume:{$reservationId}",
            fn (): int => $this->consume($reservationId),
        );

        return StockReservation::query()->with(['product', 'location'])->findOrFail((int) $result);
    }

    private function consume(int $reservationId): int
    {
        return DB::connection('period')->transaction(function () use ($reservationId): int {
            $reservation = StockReservation::query()->lockForUpdate()->findOrFail($reservationId);

            if ($reservation->status !== 'active') {
                throw new DomainException('Yalnız aktif rezervasyon tüketilebilir.');
            }

            $this->adjustReserved->handle(
                $reservation->product_id,
                $reservation->location_id,
                bcmul((string) $reservation->quantity, '-1', 3),
            );

            $reservation->status = 'consumed';
            $reservation->released_at = now();
            $reservation->save();

            return $reservation->id;
        }, attempts: 3);
    }
}
