<?php

namespace App\Modules\SalesOrders\Progress;

use App\Foundation\Clock\Clock;
use App\Foundation\Idempotency\IdempotencyStatus;
use App\Foundation\Idempotency\IdempotencyStore;
use App\Foundation\Idempotency\RequestFingerprint;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class SalesOrderProgressService
{
    private const IDEMPOTENCY_SCOPE = 'sales_order.progress';

    public function __construct(
        private IdempotencyStore $idempotency,
        private Clock $clock,
    ) {}

    public function record(
        SourceEffectIdentity $sourceEffect,
        int $salesOrderLineId,
        SalesOrderProgressType $progressType,
        string $quantityDelta,
    ): SalesOrderLineProgressEffect {
        $this->assertInsideTransaction();

        $quantityDelta = $this->positiveDecimal($quantityDelta);
        $line = SalesOrderLine::query()
            ->where('company_id', $sourceEffect->companyId)
            ->whereKey($salesOrderLineId)
            ->lockForUpdate()
            ->first();

        if (! $line instanceof SalesOrderLine) {
            throw ValidationException::withMessages([
                'sales_order_line_id' => 'Sipariş satırı aktif şirkette bulunamadı.',
            ]);
        }

        $operationKey = $sourceEffect->fingerprint();
        $fingerprint = RequestFingerprint::fromPayload([
            'company_id' => $sourceEffect->companyId,
            'sales_order_id' => (int) $line->sales_order_id,
            'sales_order_line_id' => $salesOrderLineId,
            'progress_type' => $progressType->value,
            'quantity_delta' => $quantityDelta,
        ]);
        $claim = $this->idempotency->claim(self::IDEMPOTENCY_SCOPE, $operationKey, $fingerprint);

        if ($claim->isReplay()) {
            return $this->completedReplay($claim->status, $operationKey);
        }

        $now = $this->clock->now();

        try {
            $effect = SalesOrderLineProgressEffect::query()->create([
                'company_id' => $sourceEffect->companyId,
                'sales_order_id' => (int) $line->sales_order_id,
                'sales_order_line_id' => $salesOrderLineId,
                'progress_type' => $progressType->value,
                'quantity_delta' => $quantityDelta,
                'reversal_of_progress_effect_id' => null,
                'operation_key' => $operationKey,
                'request_fingerprint' => $fingerprint->value,
                'source_type' => $sourceEffect->sourceType,
                'source_id' => $sourceEffect->sourceId,
                'effect_type' => $sourceEffect->effectType,
                'occurred_at' => $now,
                'created_at' => $now,
            ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23514') {
                throw ValidationException::withMessages([
                    'quantity_delta' => 'Sipariş progress işlemi sipariş miktarı sınırlarını aşamaz.',
                ]);
            }

            throw $exception;
        }

        $this->idempotency->complete($claim);

        return $effect;
    }

    public function reverse(
        SourceEffectIdentity $sourceEffect,
        int $progressEffectId,
    ): SalesOrderLineProgressEffect {
        $this->assertInsideTransaction();

        $original = SalesOrderLineProgressEffect::query()
            ->where('company_id', $sourceEffect->companyId)
            ->whereKey($progressEffectId)
            ->lockForUpdate()
            ->first();

        if (! $original instanceof SalesOrderLineProgressEffect) {
            throw ValidationException::withMessages([
                'progress_effect_id' => 'Terslenecek sipariş progress effect aktif şirkette bulunamadı.',
            ]);
        }

        if ($original->reversal_of_progress_effect_id !== null || str_starts_with((string) $original->quantity_delta, '-')) {
            throw ValidationException::withMessages([
                'progress_effect_id' => 'Bir reversal effect tekrar terslenemez.',
            ]);
        }

        $progressType = $original->progress_type;
        if (! $progressType instanceof SalesOrderProgressType) {
            throw new LogicException('Persisted sales order progress type is invalid.');
        }

        $operationKey = $sourceEffect->fingerprint();
        $fingerprint = RequestFingerprint::fromPayload([
            'company_id' => $sourceEffect->companyId,
            'reversal_of_progress_effect_id' => (int) $original->getKey(),
        ]);
        $claim = $this->idempotency->claim(self::IDEMPOTENCY_SCOPE, $operationKey, $fingerprint);

        if ($claim->isReplay()) {
            return $this->completedReplay($claim->status, $operationKey);
        }

        $existingReversal = SalesOrderLineProgressEffect::query()
            ->where('reversal_of_progress_effect_id', $original->getKey())
            ->first();
        if ($existingReversal instanceof SalesOrderLineProgressEffect) {
            throw ValidationException::withMessages([
                'progress_effect_id' => 'Bu sipariş progress effect daha önce terslendi.',
            ]);
        }

        $now = $this->clock->now();

        try {
            $effect = SalesOrderLineProgressEffect::query()->create([
                'company_id' => (int) $original->company_id,
                'sales_order_id' => (int) $original->sales_order_id,
                'sales_order_line_id' => (int) $original->sales_order_line_id,
                'progress_type' => $progressType->value,
                'quantity_delta' => '-'.(string) $original->quantity_delta,
                'reversal_of_progress_effect_id' => (int) $original->getKey(),
                'operation_key' => $operationKey,
                'request_fingerprint' => $fingerprint->value,
                'source_type' => $sourceEffect->sourceType,
                'source_id' => $sourceEffect->sourceId,
                'effect_type' => $sourceEffect->effectType,
                'occurred_at' => $now,
                'created_at' => $now,
            ]);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23505', '23514'], true)) {
                throw ValidationException::withMessages([
                    'progress_effect_id' => 'Sipariş progress reversal yalnız orijinal pozitif effecti tam ve tek sefer tersleyebilir.',
                ]);
            }

            throw $exception;
        }

        $this->idempotency->complete($claim);

        return $effect;
    }

    private function completedReplay(IdempotencyStatus $status, string $operationKey): SalesOrderLineProgressEffect
    {
        if ($status !== IdempotencyStatus::Completed) {
            throw new LogicException('Sipariş progress idempotency kaydı tamamlanmamış durumda bırakılamaz.');
        }

        $existing = SalesOrderLineProgressEffect::query()
            ->where('operation_key', $operationKey)
            ->first();

        if (! $existing instanceof SalesOrderLineProgressEffect) {
            throw new LogicException('Tamamlanmış sipariş progress idempotency kaydının effect satırı bulunamadı.');
        }

        return $existing;
    }

    private function positiveDecimal(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages([
                'quantity_delta' => 'Sipariş progress miktarı pozitif ve en fazla 6 ondalıklı geçerli bir sayı olmalıdır.',
            ]);
        }

        $integerPart = explode('.', $value, 2)[0];
        if (strlen(ltrim($integerPart, '0')) > 14) {
            throw ValidationException::withMessages([
                'quantity_delta' => 'Sipariş progress miktarı desteklenen sayısal sınırı aşıyor.',
            ]);
        }

        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) > 0 AS valid',
            [$value, $value],
        );
        if ($row === null || $row->valid !== true) {
            throw ValidationException::withMessages([
                'quantity_delta' => 'Sipariş progress miktarı sıfırdan büyük olmalıdır.',
            ]);
        }

        return (string) $row->value;
    }

    private function assertInsideTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Sipariş progress effect aynı business transaction içinde çalışmalıdır.');
        }
    }
}
