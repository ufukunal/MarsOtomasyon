<?php

namespace App\Modules\B2B\Portal;

use App\Modules\B2B\Enums\B2BPermission;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final readonly class B2BOrderController
{
    public function __construct(private B2BPortalAccess $access, private B2BOrderService $orders) {}

    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:128']]);
        $raw = $request->session()->get('b2b_cart', []);
        $cart = [];
        if (is_array($raw)) {
            foreach ($raw as $code => $quantity) {
                if (is_string($code) && is_string($quantity)) {
                    $cart[$code] = $quantity;
                }
            }
        }
        $result = $this->orders->submit($cart, (string) $validated['idempotency_key']);
        if (! $result->replayed) {
            $request->session()->forget('b2b_cart');
        }

        return redirect()->route('b2b.orders.show', (string) $result->order->number)
            ->with('status', $result->replayed ? 'Bu sipariş gönderimi daha önce işlendi; mevcut sipariş gösteriliyor.' : 'Sipariş oluşturuldu.')
            ->with('warning', $result->warning);
    }

    public function index(): View
    {
        $this->access->authorize(B2BPermission::ViewOrderHistory);
        $user = $this->access->user();
        $orders = SalesOrder::query()
            ->where('company_id', $user->company_id)
            ->where('account_id', $user->account_id)
            ->orderByDesc('id')
            ->paginate(30);

        return view('b2b.orders.index', compact('orders'));
    }

    public function show(string $order): View
    {
        $this->access->authorize(B2BPermission::ViewOrderHistory);
        $user = $this->access->user();
        $orderModel = SalesOrder::query()
            ->where('company_id', $user->company_id)
            ->where('account_id', $user->account_id)
            ->where('number', $order)
            ->with('lines')
            ->firstOrFail();

        return view('b2b.orders.show', ['order' => $orderModel]);
    }
}
