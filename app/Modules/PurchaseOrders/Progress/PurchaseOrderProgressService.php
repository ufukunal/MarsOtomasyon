<?php

namespace App\Modules\PurchaseOrders\Progress;

use App\Foundation\Clock\Clock;
use App\Foundation\Idempotency\IdempotencyStatus;
use App\Foundation\Idempotency\IdempotencyStore;
use App\Foundation\Idempotency\RequestFingerprint;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderProgressType;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLine;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLineProgressEffect;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class PurchaseOrderProgressService
{
    private const IDEMPOTENCY_SCOPE = 'purchase_order.progress';

    public function __construct(
        private IdempotencyStore $idempotency,
        private Clock $clock,
    ) {}

    public function record(
        SourceEffectIdentity $sourceEffect,
        int $purchaseOrderLineId,
        PurchaseOrderProgressType $progressType,
        string $quantityDelta,
    ): PurchaseOrderLineProgressEffect {
        $this->assertInsideTransaction();
        $quantityDelta = $this->signedDecimal($quantityDelta);

        $line = PurchaseOrderLine::query()
            ->where('company_id', $sourceEffect->companyId)
            ->whereKey($purchaseOrderLineId)
            ->lockForUpdate()
            ->first();
        if (! $line instanceof PurchaseOrderLine) {
            throw ValidationException::withMessages([
                'purchase_order_line_id' => 'Satınalma siparişi satırı aktif şirkette bulunamadı.',
            ]);
        }

        $order = PurchaseOrder::query()
            ->where('company_id', $sourceEffect->companyId)
            ->whereKey($line->purchase_order_id)
            ->lockForUpdate()
            ->first();
        if (! $order instanceof PurchaseOrder) {
            throw ValidationException::withMessages(['purchase_order' => 'Satınalma siparişi bulunamadı.']);
        }

        $negative = str_starts_with($quantityDelta, '-');
        if (! $negative && ! $order->isOpen()) {
            throw ValidationException::withMessages(['purchase_order' => 'Pozitif satınalma progress işlemi açık sipariş gerektirir.']);
        }
        if ($negative && (
            $sourceEffect->sourceType !== 'purchase_return_line'
            || ! in_array($progressType, [PurchaseOrderProgressType::Received, PurchaseOrderProgressType::Invoiced], true)
            || (! $order->isOpen() && ! $order->isClosed())
        )) {
            throw ValidationException::withMessages(['quantity_delta' => 'Negatif satınalma progress yalnız açık/kapalı siparişte purchase return düzeltmesi olabilir.']);
        }

        $operationKey = $sourceEffect->fingerprint();
        $fingerprint = RequestFingerprint::fromPayload([
            'company_id' => $sourceEffect->companyId,
            'purchase_order_id' => (int) $line->purchase_order_id,
            'purchase_order_line_id' => $purchaseOrderLineId,
            'progress_type' => $progressType->value,
            'quantity_delta' => $quantityDelta,
        ]);
        $claim = $this->idempotency->claim(self::IDEMPOTENCY_SCOPE, $operationKey, $fingerprint);

        if ($claim->isReplay()) {
            return $this->completedReplay($claim->status, $operationKey);
        }

        $now = $this->clock->now();
        try {
            $effect = PurchaseOrderLineProgressEffect::query()->create([
                'company_id' => $sourceEffect->companyId,
                'purchase_order_id' => (int) $line->purchase_order_id,
                'purchase_order_line_id' => $purchaseOrderLineId,
                'progress_type' => $progressType->value,
                'quantity_delta' => $quantityDelta,
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
                    'quantity_delta' => 'Satınalma siparişi progress işlemi lifecycle veya net miktar sınırlarını aşamaz.',
                ]);
            }
            throw $exception;
        }

        $this->idempotency->complete($claim);

        return $effect;
    }

    private function completedReplay(IdempotencyStatus $status, string $operationKey): PurchaseOrderLineProgressEffect
    {
        if ($status !== IdempotencyStatus::Completed) {
            throw new LogicException('Satınalma siparişi progress idempotency kaydı tamamlanmamış durumda bırakılamaz.');
        }

        $existing = PurchaseOrderLineProgressEffect::query()->where('operation_key', $operationKey)->first();
        if (! $existing instanceof PurchaseOrderLineProgressEffect) {
            throw new LogicException('Tamamlanmış satınalma siparişi progress idempotency kaydının effect satırı bulunamadı.');
        }

        return $existing;
    }

    private function signedDecimal(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^-?\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages([
                'quantity_delta' => 'Satınalma siparişi progress miktarı sıfırdan farklı ve en fazla 6 ondalıklı geçerli bir sayı olmalıdır.',
            ]);
        }

        $integerPart = explode('.', ltrim($value, '-'), 2)[0];
        if (strlen(ltrim($integerPart, '0')) > 14) {
            throw ValidationException::withMessages([
                'quantity_delta' => 'Satınalma siparişi progress miktarı desteklenen sayısal sınırı aşıyor.',
            ]);
        }

        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) <> 0 AS valid',
            [$value, $value],
        );
        if ($row === null || $row->valid !== true) {
            throw ValidationException::withMessages([
                'quantity_delta' => 'Satınalma siparişi progress miktarı sıfır olamaz.',
            ]);
        }

        return (string) $row->value;
    }

    private function assertInsideTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Satınalma siparişi progress effect aynı business transaction içinde çalışmalıdır.');
        }
    }
}
