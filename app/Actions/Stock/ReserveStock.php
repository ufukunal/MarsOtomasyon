<?php

namespace App\Actions\Stock;

use App\DataObjects\ReservationResult;
use App\Models\Period\StockBalance;
use App\Models\Period\StockReservation;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReserveStock
{
    public function __construct(private readonly AdjustReservedBalance $adjustReserved) {}

    /**
     * @param  list<int>  $orderedLocationIds
     */
    public function handle(
        int $productId,
        string $requestedQuantity,
        array $orderedLocationIds,
        string $documentType,
        int $documentId,
        int $documentLineId,
        string $idempotencyKey,
        ?int $actorUserId = null,
        ?string $actorUserName = null,
    ): ReservationResult {
        MutationAuthorizer::authorize('reservations.create');
        PeriodContext::ensureWritable();

        if (bccomp($requestedQuantity, '0', 3) <= 0) {
            throw new DomainException('Rezervasyon miktarı pozitif olmalıdır.');
        }

        $locationIds = array_values(array_unique(array_map('intval', $orderedLocationIds)));

        if ($locationIds === []) {
            return new ReservationResult([], '0.000', bcadd($requestedQuantity, '0', 3));
        }

        $result = IdempotencyKey::run(
            $idempotencyKey,
            "reservation.reserve:{$documentType}:{$documentId}:{$documentLineId}:{$productId}",
            fn (): array => $this->reserve(
                $productId,
                $requestedQuantity,
                $locationIds,
                $documentType,
                $documentId,
                $documentLineId,
                $actorUserId,
                $actorUserName,
            ),
        );

        return new ReservationResult(
            reservationIds: array_map('intval', $result['reservation_ids']),
            reservedQuantity: (string) $result['reserved_quantity'],
            openQuantity: (string) $result['open_quantity'],
        );
    }

    /**
     * @param  list<int>  $orderedLocationIds
     * @return array{reservation_ids:list<int>,reserved_quantity:string,open_quantity:string}
     */
    private function reserve(
        int $productId,
        string $requestedQuantity,
        array $orderedLocationIds,
        string $documentType,
        int $documentId,
        int $documentLineId,
        ?int $actorUserId,
        ?string $actorUserName,
    ): array {
        return DB::connection('period')->transaction(function () use (
            $productId,
            $requestedQuantity,
            $orderedLocationIds,
            $documentType,
            $documentId,
            $documentLineId,
            $actorUserId,
            $actorUserName,
        ): array {
            $lockOrder = $orderedLocationIds;
            sort($lockOrder, SORT_NUMERIC);

            $balances = StockBalance::query()
                ->where('product_id', $productId)
                ->whereIn('location_id', $lockOrder)
                ->orderBy('location_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('location_id');

            $remaining = bcadd($requestedQuantity, '0', 3);
            $reservationIds = [];

            foreach ($orderedLocationIds as $locationId) {
                if (bccomp($remaining, '0', 3) <= 0) {
                    break;
                }

                $balance = $balances->get($locationId);

                if (! $balance) {
                    continue;
                }

                $available = $balance->available();

                if (bccomp($available, '0', 3) <= 0) {
                    continue;
                }

                $reserve = bccomp($remaining, $available, 3) <= 0
                    ? $remaining
                    : $available;

                $reservation = StockReservation::query()->create([
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'quantity' => $reserve,
                    'document_type' => $documentType,
                    'document_id' => $documentId,
                    'document_line_id' => $documentLineId,
                    'status' => 'active',
                    'created_by' => $actorUserId,
                    'created_by_name' => $actorUserName,
                ]);

                $this->adjustReserved->handle($productId, $locationId, $reserve);
                $reservationIds[] = $reservation->id;
                $remaining = bcsub($remaining, $reserve, 3);
            }

            return [
                'reservation_ids' => $reservationIds,
                'reserved_quantity' => bcsub($requestedQuantity, $remaining, 3),
                'open_quantity' => $remaining,
            ];
        }, attempts: 3);
    }
}
