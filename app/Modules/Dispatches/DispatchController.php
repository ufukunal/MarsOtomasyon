<?php

namespace App\Modules\Dispatches;

use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Dispatches\Actions\CreateDispatch;
use App\Modules\Dispatches\Actions\DispatchDraftData;
use App\Modules\Dispatches\Actions\DispatchLineData;
use App\Modules\Dispatches\Actions\FinalizeDispatch;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Dispatches\Models\DispatchOrderLineCapacity;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final readonly class DispatchController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CreateDispatch $createDispatch,
        private FinalizeDispatch $finalizeDispatch,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $query = Dispatch::query()
            ->with(['account', 'salesOrder'])
            ->where('company_id', $this->companyId());

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->whereRaw('number ILIKE ?', [$like])
                    ->orWhereRaw('carrier_name ILIKE ?', [$like])
                    ->orWhereHas('account', fn ($account) => $account->whereRaw('legal_name ILIKE ?', [$like]))
                    ->orWhereHas('salesOrder', fn ($order) => $order->whereRaw('number ILIKE ?', [$like]));
            });
        }

        return view('dispatches.index', [
            'dispatches' => $query->orderByDesc('dispatch_date')->orderByDesc('id')->paginate(50)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        $companyId = $this->companyId();
        $orderId = $request->query('sales_order_id');
        $selectedOrder = null;
        $addresses = collect();
        $capacities = collect();

        if (is_numeric($orderId)) {
            $selectedOrder = SalesOrder::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $orderId)
                ->with(['account', 'lines.warehouse', 'lines.location'])
                ->firstOrFail();
            $addresses = AccountAddress::query()
                ->where('company_id', $companyId)
                ->where('account_id', $selectedOrder->account_id)
                ->where('type', AccountAddressType::Shipping->value)
                ->orderByDesc('is_default')
                ->orderBy('label')
                ->get();
            $capacities = DispatchOrderLineCapacity::query()
                ->where('company_id', $companyId)
                ->where('sales_order_id', $selectedOrder->getKey())
                ->get()
                ->keyBy('sales_order_line_id');
        }

        return view('dispatches.create', [
            'orders' => SalesOrder::query()
                ->where('company_id', $companyId)
                ->with('account')
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
            'selectedOrder' => $selectedOrder,
            'addresses' => $addresses,
            'capacities' => $capacities,
            'warehouses' => Warehouse::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->with(['locations' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'series_code' => ['nullable', 'string', 'max:64'],
            'sales_order_id' => ['required', 'integer'],
            'source_address_id' => ['required', 'integer'],
            'dispatch_date' => ['required', 'date_format:Y-m-d'],
            'carrier_name' => ['nullable', 'string', 'max:200'],
            'carrier_service' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.sales_order_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'lines.*.allocation_key' => ['nullable', 'string', 'max:64', 'regex:/^[1-9][0-9]*:[1-9][0-9]*$/D'],
        ]);

        $lines = [];
        foreach ($validated['lines'] as $line) {
            [$warehouseId, $locationId] = $this->allocationPair($line['allocation_key'] ?? null);
            $lines[] = new DispatchLineData(
                salesOrderLineId: (int) $line['sales_order_line_id'],
                quantity: (string) $line['quantity'],
                warehouseId: $warehouseId,
                locationId: $locationId,
            );
        }

        $dispatch = $this->createDispatch->handle(new DispatchDraftData(
            salesOrderId: (int) $validated['sales_order_id'],
            sourceAddressId: (int) $validated['source_address_id'],
            dispatchDate: (string) $validated['dispatch_date'],
            carrierName: isset($validated['carrier_name']) ? (string) $validated['carrier_name'] : null,
            carrierService: isset($validated['carrier_service']) ? (string) $validated['carrier_service'] : null,
            trackingNumber: isset($validated['tracking_number']) ? (string) $validated['tracking_number'] : null,
            note: isset($validated['note']) ? (string) $validated['note'] : null,
            lines: $lines,
        ), (string) ($validated['series_code'] ?? 'default'));

        return redirect()->route('dispatches.show', $dispatch->getKey())->with('status', 'Taslak irsaliye oluşturuldu.');
    }

    public function finalize(int $dispatch): RedirectResponse
    {
        $record = $this->finalizeDispatch->handle($dispatch);

        return redirect()
            ->route('dispatches.show', $record->getKey())
            ->with('status', 'İrsaliye kesinleştirildi; stok çıkışı ve sipariş sevk miktarı işlendi.');
    }

    public function show(int $dispatch): View
    {
        $companyId = $this->companyId();
        $record = Dispatch::query()
            ->where('company_id', $companyId)
            ->whereKey($dispatch)
            ->with(['account', 'salesOrder', 'sourceAddress', 'lines.salesOrderLine', 'lines.warehouse', 'lines.location'])
            ->firstOrFail();

        return view('dispatches.show', [
            'dispatch' => $record,
            'capacities' => DispatchOrderLineCapacity::query()
                ->where('company_id', $companyId)
                ->where('sales_order_id', $record->sales_order_id)
                ->whereIn('sales_order_line_id', $record->lines->pluck('sales_order_line_id'))
                ->get()
                ->keyBy('sales_order_line_id'),
        ]);
    }

    /** @return array{0:?int,1:?int} */
    private function allocationPair(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [null, null];
        }

        [$warehouseId, $locationId] = explode(':', $value, 2);

        return [(int) $warehouseId, (int) $locationId];
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
