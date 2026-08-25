<?php

namespace App\Modules\Inventory\Reservations;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Foundation\Idempotency\IdempotencyStatus;
use App\Foundation\Idempotency\IdempotencyStore;
use App\Foundation\Idempotency\RequestFingerprint;
use App\Modules\Inventory\Enums\StockReservationStatus;
use App\Modules\Inventory\Models\StockReservation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class StockReservationService
{
    private const RESERVE_SCOPE = 'inventory.stock_reservation.reserve';

    private const RELEASE_SCOPE = 'inventory.stock_reservation.release';

    private const CONSUME_SCOPE = 'inventory.stock_reservation.consume';

    public function __construct(
        private IdempotencyStore $idempotency,
        private Clock $clock,
    ) {}

    public function reserve(
        SourceEffectIdentity $sourceEffect,
        int $productId,
        int $warehouseId,
        int $locationId,
        string $quantity,
    ): StockReservationActionResult
    {
        $this->assertInsideTransaction();
        $quantity = $this->positiveDecimal($quantity);

        $fingerprint = RequestFingerprint::fromPayload([
            'company_id' => $sourceEffect->companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'quantity' => $quantity,
        ]);
        $claim = $this->idempotency->claim(self::RESERVE_SCOPE, $sourceEffect->fingerprint(), $fingerprint);

        if ($claim->isReplay()) {
            $this->assertCompletedReplay($claim->status);

            $existing = StockReservation::query()
                ->where('company_id', $sourceEffect->companyId)
                ->where('reserve_source_type', $sourceEffect->sourceType)
                ->where('reserve_source_id', $sourceEffect->sourceId)
                ->where('reserve_effect_type', $sourceEffect->effectType)
                ->first();

            if ($existing === null) {
                throw new LogicException('Tamamlanmış rezervasyon idempotency kaydının reservation satırı bulunamadı.');
            }

            return new StockReservationActionResult($existing, true);
        }

        $now = $this->clock->now();

        try {
            $reservation = StockReservation::query()->create([
                'company_id' => $sourceEffect->companyId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'status' => StockReservationStatus::Active->value,
                'reserve_source_type' => $sourceEffect->sourceType,
                'reserve_source_id' => $sourceEffect->sourceId,
                'reserve_effect_type' => $sourceEffect->effectType,
                'reserved_at' => $now,
            ]);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'stock reservation exceeds available quantity')) {
                throw ValidationException::withMessages([
                    'quantity' => 'Rezervasyon kullanılabilir stok miktarını aşamaz.',
                ]);
            }

            throw $exception;
        }

        $this->idempotency->complete($claim);

        return new StockReservationActionResult($reservation, false);
    }

    public function release(SourceEffectIdentity $sourceEffect, int $reservationId): StockReservationActionResult
    {
        return $this->transition(
            sourceEffect: $sourceEffect,
            reservationId: $reservationId,
            targetStatus: StockReservationStatus::Released,
            scope: self::RELEASE_SCOPE,
        );
    }

    public function consume(SourceEffectIdentity $sourceEffect, int $reservationId): StockReservationActionResult
    {
        return $this->transition(
            sourceEffect: $sourceEffect,
            reservationId: $reservationId,
            targetStatus: StockReservationStatus::Consumed,
            scope: self::CONSUME_SCOPE,
        );
    }

    private function transition(
        SourceEffectIdentity $sourceEffect,
        int $reservationId,
        StockReservationStatus $targetStatus,
        string $scope,
    ): StockReservationActionResult
    {
        $this->assertInsideTransaction();

        $fingerprint = RequestFingerprint::fromPayload([
            'company_id' => $sourceEffect->companyId,
            'reservation_id' => $reservationId,
            'target_status' => $targetStatus->value,
        ]);
        $claim = $this->idempotency->claim($scope, $sourceEffect->fingerprint(), $fingerprint);

        if ($claim->isReplay()) {
            $this->assertCompletedReplay($claim->status);
            $existing = StockReservation::query()
                ->where('company_id', $sourceEffect->companyId)
                ->find($reservationId);

            if ($existing === null || $this->matchesTerminalEffect($existing, $targetStatus, $sourceEffect) === false) {
                throw new LogicException('Tamamlanmış rezervasyon lifecycle idempotency kaydı ile reservation state uyuşmuyor.');
            }

            return new StockReservationActionResult($existing, true);
        }

        $reservation = StockReservation::query()
            ->where('company_id', $sourceEffect->companyId)
            ->whereKey($reservationId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($reservation->statusEnum() !== StockReservationStatus::Active) {
            throw ValidationException::withMessages([
                'reservation' => 'Yalnız aktif stok rezervasyonu serbest bırakılabilir veya tüketilebilir.',
            ]);
        }

        $now = $this->clock->now();
        $prefix = $targetStatus === StockReservationStatus::Released ? 'release' : 'consume';
        $timestampColumn = $targetStatus === StockReservationStatus::Released ? 'released_at' : 'consumed_at';

        $reservation->fill([
            'status' => $targetStatus->value,
            $prefix.'_source_type' => $sourceEffect->sourceType,
            $prefix.'_source_id' => $sourceEffect->sourceId,
            $prefix.'_effect_type' => $sourceEffect->effectType,
            $timestampColumn => $now,
        ]);
        $reservation->save();

        $this->idempotency->complete($claim);

        return new StockReservationActionResult($reservation, false);
    }

    private function matchesTerminalEffect(
        StockReservation $reservation,
        StockReservationStatus $targetStatus,
        SourceEffectIdentity $sourceEffect,
    ): bool
    {
        if ($reservation->statusEnum() !== $targetStatus) {
            return false;
        }

        $prefix = $targetStatus === StockReservationStatus::Released ? 'release' : 'consume';

        return $reservation->getAttribute($prefix.'_source_type') === $sourceEffect->sourceType
            && $reservation->getAttribute($prefix.'_source_id') === $sourceEffect->sourceId
            && $reservation->getAttribute($prefix.'_effect_type') === $sourceEffect->effectType;
    }

    private function positiveDecimal(string $quantity): string
    {
        $quantity = trim($quantity);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $quantity) !== 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Rezervasyon miktarı sıfırdan büyük ve en fazla 6 ondalıklı olmalıdır.',
            ]);
        }

        $integerPart = explode('.', $quantity, 2)[0];
        if (strlen(ltrim($integerPart, '0')) > 14) {
            throw ValidationException::withMessages([
                'quantity' => 'Rezervasyon miktarı desteklenen aralığı aşıyor.',
            ]);
        }

        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) > 0 AS valid',
            [$quantity, $quantity],
        );
        if ($row === null || $row->valid !== true) {
            throw ValidationException::withMessages([
                'quantity' => 'Rezervasyon miktarı sıfırdan büyük olmalıdır.',
            ]);
        }

        return (string) $row->value;
    }

    private function assertCompletedReplay(IdempotencyStatus $status): void
    {
        if ($status !== IdempotencyStatus::Completed) {
            throw new LogicException('Rezervasyon idempotency kaydı tamamlanmamış durumda bırakılamaz.');
        }
    }

    private function assertInsideTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Stok rezervasyonu aynı business transaction içinde çalışmalıdır.');
        }
    }
}
