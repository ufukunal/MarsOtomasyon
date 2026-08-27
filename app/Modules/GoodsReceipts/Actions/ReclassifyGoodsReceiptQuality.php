<?php

namespace App\Modules\GoodsReceipts\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\GoodsReceipts\Enums\GoodsReceiptQualityDisposition;
use App\Modules\GoodsReceipts\Enums\GoodsReceiptStatus;
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use App\Modules\GoodsReceipts\Models\GoodsReceiptLine;
use App\Modules\GoodsReceipts\Models\GoodsReceiptQualityEffect;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderProgressType;
use App\Modules\PurchaseOrders\Progress\PurchaseOrderProgressService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ReclassifyGoodsReceiptQuality
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private StockMovementPoster $stockMovements,
        private PurchaseOrderProgressService $progress,
        private AuditRecorder $audit,
        private Clock $clock,
    ) {}

    public function handle(
        int $goodsReceiptId,
        int $goodsReceiptLineId,
        GoodsReceiptQualityDisposition $disposition,
        string $quantity,
        ?string $note = null,
    ): GoodsReceiptQualityEffect {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $quantity = $this->positiveDecimal($quantity);
        $note = $this->normalizeNote($note);

        return DB::transaction(function () use (
            $companyId,
            $goodsReceiptId,
            $goodsReceiptLineId,
            $disposition,
            $quantity,
            $note,
        ): GoodsReceiptQualityEffect {
            $receipt = GoodsReceipt::query()
                ->where('company_id', $companyId)
                ->whereKey($goodsReceiptId)
                ->lockForUpdate()
                ->first();

            if (! $receipt instanceof GoodsReceipt) {
                throw ValidationException::withMessages(['goods_receipt' => 'Mal kabul aktif şirkette bulunamadı.']);
            }
            if ($receipt->statusEnum() !== GoodsReceiptStatus::Finalized) {
                throw ValidationException::withMessages(['goods_receipt' => 'Kalite sınıflandırması yalnız kesinleşmiş mal kabul üzerinde yapılabilir.']);
            }

            $line = GoodsReceiptLine::query()
                ->where('company_id', $companyId)
                ->where('goods_receipt_id', $goodsReceiptId)
                ->whereKey($goodsReceiptLineId)
                ->lockForUpdate()
                ->first();

            if (! $line instanceof GoodsReceiptLine) {
                throw ValidationException::withMessages(['goods_receipt_line_id' => 'Mal kabul satırı aktif belgede bulunamadı.']);
            }

            $before = $this->custody($companyId, $goodsReceiptLineId);
            $actorId = Auth::id();
            if (! is_int($actorId)) {
                throw new LogicException('Quality reclassification requires an authenticated actor.');
            }

            $now = $this->clock->now();
            try {
                $effect = GoodsReceiptQualityEffect::query()->create([
                    'company_id' => $companyId,
                    'goods_receipt_id' => $goodsReceiptId,
                    'goods_receipt_line_id' => $goodsReceiptLineId,
                    'disposition' => $disposition,
                    'quantity' => $quantity,
                    'note' => $note,
                    'created_by_user_id' => $actorId,
                    'occurred_at' => $now,
                    'created_at' => $now,
                ]);
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() === '23514') {
                    throw ValidationException::withMessages([
                        'quantity' => 'Kalite sınıflandırma miktarı kalan pending miktarı aşamaz.',
                    ]);
                }

                throw $exception;
            }

            if ($disposition === GoodsReceiptQualityDisposition::Accepted) {
                $sourceId = (string) $effect->getKey();
                $this->stockMovements->post(new PostStockMovementData(
                    sourceEffect: new SourceEffectIdentity($companyId, 'goods_receipt_quality_effect', $sourceId, 'stock.in'),
                    productId: (int) $line->product_id,
                    warehouseId: (int) $line->warehouse_id,
                    locationId: (int) $line->location_id,
                    movementType: StockMovementType::GoodsReceiptIn,
                    quantity: $quantity,
                    unitCost: (string) $line->provisional_unit_cost,
                    note: 'Mal kabul kalite kabulü '.$receipt->number,
                ));

                $this->progress->record(
                    new SourceEffectIdentity($companyId, 'goods_receipt_quality_effect', $sourceId, 'progress.receive'),
                    (int) $line->purchase_order_line_id,
                    PurchaseOrderProgressType::Received,
                    $quantity,
                );
            }

            $after = $this->custody($companyId, $goodsReceiptLineId);
            $this->audit->record(
                AuditAction::GoodsReceiptQualityReclassified,
                AuditTargetType::GoodsReceipt,
                $receipt->getKey(),
                before: $before,
                after: $after,
                metadata: [
                    'goods_receipt_line_id' => $goodsReceiptLineId,
                    'quality_effect_id' => $effect->getKey(),
                    'disposition' => $disposition->value,
                    'quantity' => $quantity,
                    'note' => $note,
                ],
            );

            return $effect;
        });
    }

    /** @return array<string, string|int> */
    private function custody(int $companyId, int $lineId): array
    {
        $row = DB::table('goods_receipt_line_quality')
            ->where('company_id', $companyId)
            ->where('goods_receipt_line_id', $lineId)
            ->first();

        if ($row === null) {
            throw new LogicException('Goods receipt quality projection row not found.');
        }

        return [
            'goods_receipt_line_id' => $lineId,
            'accepted_quantity' => (string) $row->accepted_quantity,
            'pending_quantity' => (string) $row->pending_quantity,
            'rejected_quantity' => (string) $row->rejected_quantity,
        ];
    }

    private function positiveDecimal(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages(['quantity' => 'Miktar pozitif ve en fazla 6 ondalıklı geçerli bir sayı olmalıdır.']);
        }

        $integerPart = explode('.', $value, 2)[0];
        if (strlen(ltrim($integerPart, '0')) > 14) {
            throw ValidationException::withMessages(['quantity' => 'Miktar desteklenen sayısal sınırı aşıyor.']);
        }

        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) > 0 AS valid',
            [$value, $value],
        );
        if ($row === null || $row->valid !== true) {
            throw ValidationException::withMessages(['quantity' => 'Miktar sıfırdan büyük olmalıdır.']);
        }

        return (string) $row->value;
    }

    private function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $note = trim($note);
        if ($note === '') {
            return null;
        }
        if (mb_strlen($note) > 1000) {
            throw ValidationException::withMessages(['note' => 'Kalite notu en fazla 1000 karakter olabilir.']);
        }

        return $note;
    }
}
