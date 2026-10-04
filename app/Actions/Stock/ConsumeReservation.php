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

    public function handle(int $reservationId, string $idempotencyKey, ?string $quantity = null): StockReservation
    {
        MutationAuthorizer::authorize('reservations.update');
        PeriodContext::ensureWritable();

        $result = IdempotencyKey::run(
            $idempotencyKey,
            "reservation.consume:{$reservationId}",
            fn (): int => $this->consume($reservationId, $quantity),
        );

        return StockReservation::query()->with(['product', 'location'])->findOrFail((int) $result);
    }

    private function consume(int $reservationId, ?string $quantity): int
    {
        return DB::connection('period')->transaction(function () use ($reservationId, $quantity): int {
            $reservation = StockReservation::query()->lockForUpdate()->findOrFail($reservationId);

            if ($reservation->status !== 'active') {
                throw new DomainException('Yalnız aktif rezervasyon tüketilebilir.');
            }

            $consume = bcadd($quantity ?? (string) $reservation->quantity, '0', 3);

            if (bccomp($consume, '0', 3) <= 0 || bccomp($consume, (string) $reservation->quantity, 3) > 0) {
                throw new DomainException('Rezervasyon tüketim miktarı geçersiz.');
            }

            $this->adjustReserved->handle(
                $reservation->product_id,
                $reservation->location_id,
                bcmul($consume, '-1', 3),
            );

            if (bccomp($consume, (string) $reservation->quantity, 3) === 0) {
                $reservation->status = 'consumed';
                $reservation->released_at = now();
                $reservation->save();

                return $reservation->id;
            }

            $reservation->quantity = bcsub((string) $reservation->quantity, $consume, 3);
            $reservation->version = (int) $reservation->version + 1;
            $reservation->save();

            $consumed = StockReservation::query()->create([
                'product_id' => $reservation->product_id,
                'location_id' => $reservation->location_id,
                'quantity' => $consume,
                'document_type' => $reservation->document_type,
                'document_id' => $reservation->document_id,
                'document_line_id' => $reservation->document_line_id,
                'status' => 'consumed',
                'created_by' => $reservation->created_by,
                'created_by_name' => $reservation->created_by_name,
                'released_at' => now(),
            ]);

            return $consumed->id;
        }, attempts: 3);
    }
}
