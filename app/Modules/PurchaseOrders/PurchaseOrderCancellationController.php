<?php

namespace App\Modules\PurchaseOrders;

use App\Modules\PurchaseOrders\Actions\CancelPurchaseOrderLineQuantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class PurchaseOrderCancellationController
{
    public function __construct(private CancelPurchaseOrderLineQuantity $cancelQuantity) {}

    public function __invoke(Request $request, int $purchaseOrder, int $purchaseOrderLine): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'operation_id' => ['required', 'uuid'],
        ]);

        $this->cancelQuantity->handle(
            $purchaseOrder,
            $purchaseOrderLine,
            (string) $validated['quantity'],
            (string) $validated['operation_id'],
        );

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('status', 'Satınalma siparişi açık miktarı iptal edildi.');
    }
}
