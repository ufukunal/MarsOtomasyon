<?php

namespace App\Modules\GoodsReceipts\Actions;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\GoodsReceipts\Enums\GoodsReceiptStatus;
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use App\Modules\GoodsReceipts\Models\GoodsReceiptCostAdjustment;
use App\Modules\GoodsReceipts\Models\GoodsReceiptLine;
use App\Modules\Inventory\Models\StockBalance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ApplyGoodsReceiptCostAdjustment
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
        private Clock $clock,
    ) {}

    public function handle(
        int $goodsReceiptId,
        int $goodsReceiptLineId,
        string $reference,
        string $totalValueDelta,
        ?string $note = null,
    ): GoodsReceiptCostAdjustment {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $reference = $this->reference($reference);
        $totalValueDelta = $this->signedDecimal($totalValueDelta);
        $note = $this->note($note);

        return DB::transaction(function () use (
            $companyId,
            $goodsReceiptId,
            $goodsReceiptLineId,
            $reference,
            $totalValueDelta,
            $note,
        ): GoodsReceiptCostAdjustment {
            $receipt = GoodsReceipt::query()
                ->where('company_id', $companyId)
                ->whereKey($goodsReceiptId)
                ->lockForUpdate()
                ->first();
            if (! $receipt instanceof GoodsReceipt || $receipt->statusEnum() !== GoodsReceiptStatus::Finalized) {
                throw ValidationException::withMessages([
                    'goods_receipt' => 'Maliyet düzeltmesi yalnız kesinleşmiş mal kabul üzerinde yapılabilir.',
                ]);
            }

            $line = GoodsReceiptLine::query()
                ->where('company_id', $companyId)
                ->where('goods_receipt_id', $goodsReceiptId)
                ->whereKey($goodsReceiptLineId)
                ->lockForUpdate()
                ->first();
            if (! $line instanceof GoodsReceiptLine) {
                throw ValidationException::withMessages([
                    'goods_receipt_line_id' => 'Maliyet düzeltmesi kaynak mal kabul satırı bulunamadı.',
                ]);
            }

            $existing = GoodsReceiptCostAdjustment::query()
                ->where('company_id', $companyId)
                ->where('goods_receipt_line_id', $goodsReceiptLineId)
                ->where('reference', $reference)
                ->first();
            if ($existing instanceof GoodsReceiptCostAdjustment) {
                if ((string) $existing->total_value_delta !== $totalValueDelta || $existing->note !== $note) {
                    throw ValidationException::withMessages([
                        'reference' => 'Bu maliyet referansı daha önce farklı içerikle kullanılmış.',
                    ]);
                }

                return $existing;
            }

            $eligibleQuantity = $this->eligibleQuantity($companyId, $goodsReceiptLineId);
            if (! $this->greaterThan($eligibleQuantity, '0')) {
                throw ValidationException::withMessages([
                    'goods_receipt_line_id' => 'Maliyet düzeltmesi için iade edilmemiş accepted miktar bulunmalıdır.',
                ]);
            }

            $balance = StockBalance::query()
                ->where('company_id', $companyId)
                ->where('product_id', $line->product_id)
                ->where('warehouse_id', $line->warehouse_id)
                ->where('location_id', $line->location_id)
                ->lockForUpdate()
                ->first();
            if (! $balance instanceof StockBalance) {
                throw new LogicException('Accepted mal kabul maliyet düzeltmesi için stok bakiyesi bulunamadı.');
            }

            $allocation = DB::selectOne(
                <<<'SQL'
SELECT
    CAST(LEAST(CAST(? AS numeric), CAST(? AS numeric)) AS numeric(20,6))::text AS on_hand_quantity,
    CAST(CAST(? AS numeric) - LEAST(CAST(? AS numeric), CAST(? AS numeric)) AS numeric(20,6))::text AS consumed_quantity,
    CAST(CAST(? AS numeric) * LEAST(CAST(? AS numeric), CAST(? AS numeric)) / CAST(? AS numeric) AS numeric(20,6))::text AS inventory_delta
SQL,
                [
                    $eligibleQuantity, (string) $balance->quantity,
                    $eligibleQuantity, $eligibleQuantity, (string) $balance->quantity,
                    $totalValueDelta, $eligibleQuantity, (string) $balance->quantity, $eligibleQuantity,
                ],
            );
            if ($allocation === null) {
                throw new LogicException('Maliyet düzeltmesi dağılımı hesaplanamadı.');
            }

            $after = DB::selectOne(
                <<<'SQL'
SELECT
    CAST(CAST(? AS numeric) + CAST(? AS numeric) AS numeric(20,6))::text AS inventory_value,
    CASE WHEN CAST(? AS numeric) = 0 THEN '0.000000'
         ELSE CAST((CAST(? AS numeric) + CAST(? AS numeric)) / CAST(? AS numeric) AS numeric(20,6))::text
    END AS average_unit_cost,
    CAST(CAST(? AS numeric) - CAST(? AS numeric) AS numeric(20,6))::text AS consumed_delta
SQL,
                [
                    (string) $balance->inventory_value, (string) $allocation->inventory_delta,
                    (string) $balance->quantity,
                    (string) $balance->inventory_value, (string) $allocation->inventory_delta, (string) $balance->quantity,
                    $totalValueDelta, (string) $allocation->inventory_delta,
                ],
            );
            if ($after === null || $this->lessThan((string) $after->inventory_value, '0')) {
                throw ValidationException::withMessages([
                    'total_value_delta' => 'Negatif maliyet düzeltmesi mevcut stok değerini sıfırın altına indiremez.',
                ]);
            }

            $beforeAudit = [
                'quantity' => (string) $balance->quantity,
                'average_unit_cost' => (string) $balance->average_unit_cost,
                'inventory_value' => (string) $balance->inventory_value,
            ];

            $balance->forceFill([
                'average_unit_cost' => (string) $after->average_unit_cost,
                'inventory_value' => (string) $after->inventory_value,
            ])->save();

            $actorId = Auth::id();
            if (! is_int($actorId)) {
                throw new LogicException('Maliyet düzeltmesi kimliği doğrulanmış kullanıcı gerektirir.');
            }
            $now = $this->clock->now();
            $adjustment = GoodsReceiptCostAdjustment::query()->create([
                'company_id' => $companyId,
                'goods_receipt_id' => $goodsReceiptId,
                'goods_receipt_line_id' => $goodsReceiptLineId,
                'product_id' => (int) $line->product_id,
                'warehouse_id' => (int) $line->warehouse_id,
                'location_id' => (int) $line->location_id,
                'reference' => $reference,
                'total_value_delta' => $totalValueDelta,
                'eligible_quantity' => $eligibleQuantity,
                'on_hand_quantity_basis' => (string) $allocation->on_hand_quantity,
                'consumed_quantity_basis' => (string) $allocation->consumed_quantity,
                'inventory_value_delta' => (string) $allocation->inventory_delta,
                'consumed_cost_delta' => (string) $after->consumed_delta,
                'balance_quantity_after' => (string) $balance->quantity,
                'average_unit_cost_after' => (string) $after->average_unit_cost,
                'inventory_value_after' => (string) $after->inventory_value,
                'note' => $note,
                'created_by_user_id' => $actorId,
                'occurred_at' => $now,
                'created_at' => $now,
            ]);

            $this->audit->record(
                AuditAction::GoodsReceiptCostAdjusted,
                AuditTargetType::GoodsReceipt,
                $receipt->getKey(),
                before: $beforeAudit,
                after: [
                    'quantity' => (string) $balance->quantity,
                    'average_unit_cost' => (string) $after->average_unit_cost,
                    'inventory_value' => (string) $after->inventory_value,
                ],
                metadata: [
                    'goods_receipt_line_id' => $goodsReceiptLineId,
                    'cost_adjustment_id' => $adjustment->getKey(),
                    'reference' => $reference,
                    'total_value_delta' => $totalValueDelta,
                    'inventory_value_delta' => (string) $allocation->inventory_delta,
                    'consumed_cost_delta' => (string) $after->consumed_delta,
                    'eligible_quantity' => $eligibleQuantity,
                    'on_hand_quantity_basis' => (string) $allocation->on_hand_quantity,
                    'consumed_quantity_basis' => (string) $allocation->consumed_quantity,
                ],
            );

            return $adjustment;
        });
    }

    private function eligibleQuantity(int $companyId, int $goodsReceiptLineId): string
    {
        $row = DB::selectOne(
            <<<'SQL'
SELECT CAST(
    quality.accepted_quantity - COALESCE(returned.quantity, 0)
AS numeric(20,6))::text AS quantity
FROM goods_receipt_line_quality AS quality
LEFT JOIN (
    SELECT line.company_id, line.goods_receipt_line_id, SUM(line.quantity) AS quantity
    FROM purchase_return_lines AS line
    INNER JOIN purchase_returns AS purchase_return
      ON purchase_return.company_id = line.company_id
     AND purchase_return.id = line.purchase_return_id
     AND purchase_return.status = 'finalized'
    GROUP BY line.company_id, line.goods_receipt_line_id
) AS returned
  ON returned.company_id = quality.company_id
 AND returned.goods_receipt_line_id = quality.goods_receipt_line_id
WHERE quality.company_id = ? AND quality.goods_receipt_line_id = ?
SQL,
            [$companyId, $goodsReceiptLineId],
        );

        return $row === null ? '0.000000' : (string) $row->quantity;
    }

    private function signedDecimal(string $raw): string
    {
        $value = trim($raw);
        if (preg_match('/^-?\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages(['total_value_delta' => 'Maliyet farkı sıfırdan farklı ve en fazla 6 ondalıklı sayı olmalıdır.']);
        }
        $integerPart = explode('.', ltrim($value, '-'), 2)[0];
        if (strlen(ltrim($integerPart, '0')) > 14) {
            throw ValidationException::withMessages(['total_value_delta' => 'Maliyet farkı desteklenen sayısal sınırı aşıyor.']);
        }
        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) <> 0 AS valid',
            [$value, $value],
        );
        if ($row === null || $row->valid !== true) {
            throw ValidationException::withMessages(['total_value_delta' => 'Maliyet farkı sıfır olamaz.']);
        }

        return (string) $row->value;
    }

    private function reference(string $raw): string
    {
        $value = trim($raw);
        if ($value === '' || mb_strlen($value) > 64) {
            throw ValidationException::withMessages(['reference' => 'Maliyet referansı zorunlu ve en fazla 64 karakter olmalıdır.']);
        }

        return $value;
    }

    private function note(?string $raw): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > 1000) {
            throw ValidationException::withMessages(['note' => 'Maliyet düzeltme notu en fazla 1000 karakter olabilir.']);
        }

        return $value;
    }

    private function greaterThan(string $left, string $right): bool
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) > CAST(? AS numeric) AS value', [$left, $right]);

        return $row?->value === true;
    }

    private function lessThan(string $left, string $right): bool
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) < CAST(? AS numeric) AS value', [$left, $right]);

        return $row?->value === true;
    }
}
