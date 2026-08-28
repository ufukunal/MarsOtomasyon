<?php

namespace App\Modules\PurchaseOrders\Actions;

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderProgressType;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLine;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLineProgressEffect;
use App\Modules\PurchaseOrders\Progress\PurchaseOrderProgressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CancelPurchaseOrderLineQuantity
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private PurchaseOrderProgressService $progress,
    ) {}

    public function handle(
        int $purchaseOrderId,
        int $purchaseOrderLineId,
        string $quantity,
        string $operationId,
    ): PurchaseOrderLineProgressEffect {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $quantity = $this->positiveDecimal($quantity);
        $operationId = strtolower(trim($operationId));

        if (! Str::isUuid($operationId)) {
            throw ValidationException::withMessages([
                'operation_id' => 'Sipariş miktar iptal işlem kimliği geçerli bir UUID olmalıdır.',
            ]);
        }

        return DB::transaction(function () use ($companyId, $purchaseOrderId, $purchaseOrderLineId, $quantity, $operationId): PurchaseOrderLineProgressEffect {
            $order = PurchaseOrder::query()
                ->where('company_id', $companyId)
                ->whereKey($purchaseOrderId)
                ->lockForUpdate()
                ->first();
            if (! $order instanceof PurchaseOrder) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Satınalma siparişi aktif şirkette bulunamadı.',
                ]);
            }
            if (! $order->isOpen()) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Sipariş miktarı yalnız açık satınalma siparişinde iptal edilebilir.',
                ]);
            }

            $line = PurchaseOrderLine::query()
                ->where('company_id', $companyId)
                ->where('purchase_order_id', $purchaseOrderId)
                ->whereKey($purchaseOrderLineId)
                ->lockForUpdate()
                ->first();
            if (! $line instanceof PurchaseOrderLine) {
                throw ValidationException::withMessages([
                    'purchase_order_line' => 'Satınalma siparişi satırı aktif siparişte bulunamadı.',
                ]);
            }

            return $this->progress->record(
                new SourceEffectIdentity(
                    $companyId,
                    'purchase_order_line_cancellation',
                    $operationId,
                    'progress.cancel',
                ),
                (int) $line->getKey(),
                PurchaseOrderProgressType::Cancelled,
                $quantity,
            );
        });
    }

    private function positiveDecimal(string $raw): string
    {
        $value = trim($raw);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages([
                'quantity' => 'İptal miktarı pozitif ve en fazla 6 ondalıklı olmalıdır.',
            ]);
        }

        $integerPart = explode('.', $value, 2)[0];
        if (strlen(ltrim($integerPart, '0')) > 14) {
            throw ValidationException::withMessages([
                'quantity' => 'İptal miktarı desteklenen sayısal sınırı aşıyor.',
            ]);
        }

        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) > 0 AS valid',
            [$value, $value],
        );
        if ($row === null || $row->valid !== true) {
            throw ValidationException::withMessages([
                'quantity' => 'İptal miktarı sıfırdan büyük olmalıdır.',
            ]);
        }

        return (string) $row->value;
    }
}
