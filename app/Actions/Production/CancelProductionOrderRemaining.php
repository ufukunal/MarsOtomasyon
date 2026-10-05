<?php

namespace App\Actions\Production;

use App\Models\Period\ProductionOrder;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CancelProductionOrderRemaining
{
    public function handle(ProductionOrder $order, string $idempotencyKey): ProductionOrder
    {
        MutationAuthorizer::authorize('production_orders.cancel');

        $id = IdempotencyKey::run(
            $idempotencyKey,
            'production-order.cancel-remaining:'.$order->id,
            fn (): int => DB::connection('period')->transaction(function () use ($order): int {
                $locked = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);

                if (! in_array($locked->status, ['confirmed', 'in_progress'], true)) {
                    throw new DomainException('Yalnız açık üretim emrinin kalanı iptal edilebilir.');
                }

                $remaining = $locked->remainingQuantity();

                if (bccomp($remaining, '0', 3) <= 0) {
                    throw new DomainException('İptal edilecek üretim emri kalanı bulunmuyor.');
                }

                $locked->cancelled_quantity = bcadd((string) $locked->cancelled_quantity, $remaining, 3);
                $locked->status = bccomp((string) $locked->completed_quantity, '0', 3) > 0
                    ? 'completed'
                    : 'cancelled';
                $locked->version = (int) $locked->version + 1;
                $locked->save();

                AuditContext::period(
                    'Üretim emrinin kalan miktarı iptal edildi.',
                    [
                        'production_order_id' => $locked->id,
                        'cancelled_quantity' => $remaining,
                        'status' => $locked->status,
                    ],
                    $locked,
                    'production_order_remaining_cancelled',
                );

                return (int) $locked->id;
            }, attempts: 3),
        );

        return ProductionOrder::query()->findOrFail((int) $id);
    }
}
