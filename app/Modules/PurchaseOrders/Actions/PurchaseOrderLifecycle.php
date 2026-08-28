<?php

namespace App\Modules\PurchaseOrders\Actions;

use App\Modules\PurchaseOrders\Enums\PurchaseOrderStatus;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PurchaseOrderLifecycle
{
    public function open(int $companyId, int $purchaseOrderId, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($companyId, $purchaseOrderId, $userId): PurchaseOrder {
            $order = PurchaseOrder::query()
                ->where('company_id', $companyId)
                ->whereKey($purchaseOrderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->statusEnum() === PurchaseOrderStatus::Open) {
                return $order;
            }
            if ($order->statusEnum() !== PurchaseOrderStatus::Draft) {
                throw ValidationException::withMessages(['purchase_order' => 'Yalnız taslak satınalma siparişi açılabilir.']);
            }
            if (! $order->lines()->exists()) {
                throw ValidationException::withMessages(['purchase_order' => 'Satırsız satınalma siparişi açılamaz.']);
            }
            if ($order->progressEffects()->exists()) {
                throw ValidationException::withMessages(['purchase_order' => 'Progress başlamış taslak satınalma siparişi açılamaz.']);
            }

            $order->forceFill([
                'status' => PurchaseOrderStatus::Open,
                'opened_at' => now(),
                'opened_by_user_id' => $userId,
                'closed_at' => null,
                'closed_by_user_id' => null,
            ])->save();

            return $order->refresh();
        }, 3);
    }

    public function close(int $companyId, int $purchaseOrderId, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($companyId, $purchaseOrderId, $userId): PurchaseOrder {
            $order = PurchaseOrder::query()
                ->where('company_id', $companyId)
                ->whereKey($purchaseOrderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->statusEnum() === PurchaseOrderStatus::Closed) {
                return $order;
            }
            if ($order->statusEnum() !== PurchaseOrderStatus::Open) {
                throw ValidationException::withMessages(['purchase_order' => 'Yalnız açık satınalma siparişi kapatılabilir.']);
            }

            $remaining = DB::table('purchase_order_line_progress')
                ->where('company_id', $companyId)
                ->where('purchase_order_id', $purchaseOrderId)
                ->where(function ($query): void {
                    $query->whereRaw('CAST(receive_remaining_quantity AS numeric) > 0')
                        ->orWhereRaw('CAST(invoice_remaining_quantity AS numeric) > 0');
                })
                ->exists();
            if ($remaining) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Kabul veya fatura kalan miktarı bulunan satınalma siparişi kapatılamaz. Önce kalan miktarı tamamlayın ya da iptal edin.',
                ]);
            }

            $order->forceFill([
                'status' => PurchaseOrderStatus::Closed,
                'closed_at' => now(),
                'closed_by_user_id' => $userId,
            ])->save();

            return $order->refresh();
        }, 3);
    }
}
