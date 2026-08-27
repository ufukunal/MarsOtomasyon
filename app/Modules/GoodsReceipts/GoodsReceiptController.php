<?php

namespace App\Modules\GoodsReceipts;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\GoodsReceipts\Actions\CreateGoodsReceipt;
use App\Modules\GoodsReceipts\Actions\FinalizeGoodsReceipt;
use App\Modules\GoodsReceipts\Actions\GoodsReceiptDraftData;
use App\Modules\GoodsReceipts\Actions\GoodsReceiptLineData;
use App\Modules\GoodsReceipts\Actions\UpdateGoodsReceipt;
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final readonly class GoodsReceiptController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CreateGoodsReceipt $createGoodsReceipt,
        private UpdateGoodsReceipt $updateGoodsReceipt,
        private FinalizeGoodsReceipt $finalizeGoodsReceipt,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $query = GoodsReceipt::query()
            ->with(['account', 'purchaseOrder'])
            ->where('company_id', $this->companyId());

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->whereRaw('number ILIKE ?', [$like])
                    ->orWhereHas('purchaseOrder', fn ($order) => $order->whereRaw('number ILIKE ?', [$like]))
                    ->orWhereHas('account', fn ($account) => $account->whereRaw('legal_name ILIKE ?', [$like]));
            });
        }

        return view('goods-receipts.index', [
            'receipts' => $query->orderByDesc('receipt_date')->orderByDesc('id')->paginate(50)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, null);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $receipt = $this->createGoodsReceipt->handle(
            $this->draftData($validated),
            (string) ($validated['series_code'] ?? 'default'),
        );

        return redirect()->route('goods-receipts.show', $receipt->getKey())
            ->with('status', 'Mal kabul taslağı oluşturuldu.');
    }

    public function show(int $goodsReceipt): View
    {
        $receipt = $this->receipt($goodsReceipt)->load([
            'account', 'purchaseOrder', 'lines.purchaseOrderLine.progress', 'lines.warehouse', 'lines.location',
        ]);

        return view('goods-receipts.show', ['receipt' => $receipt]);
    }

    public function edit(Request $request, int $goodsReceipt): View
    {
        $receipt = $this->receipt($goodsReceipt)->load('lines');
        if (! $receipt->isDraft()) {
            abort(409, 'Kesinleşmiş mal kabul düzenlenemez.');
        }

        return $this->form($request, $receipt);
    }

    public function update(Request $request, int $goodsReceipt): RedirectResponse
    {
        $validated = $request->validate($this->rules(includeSeries: false));
        $receipt = $this->updateGoodsReceipt->handle($goodsReceipt, $this->draftData($validated));

        return redirect()->route('goods-receipts.show', $receipt->getKey())
            ->with('status', 'Mal kabul taslağı güncellendi.');
    }

    public function finalize(int $goodsReceipt): RedirectResponse
    {
        $receipt = $this->finalizeGoodsReceipt->handle($goodsReceipt);

        return redirect()->route('goods-receipts.show', $receipt->getKey())
            ->with('status', 'Mal kabul kesinleştirildi; accepted miktar stok ve satınalma progress ledgerına işlendi.');
    }

    private function form(Request $request, ?GoodsReceipt $receipt): View
    {
        $companyId = $this->companyId();
        $orders = PurchaseOrder::query()
            ->where('company_id', $companyId)
            ->with(['account', 'lines.progress'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();

        $selectedId = $request->old('purchase_order_id');
        if (! is_numeric($selectedId)) {
            $selectedId = $receipt?->purchase_order_id ?? $request->query('purchase_order_id');
        }
        $selectedOrderId = is_numeric($selectedId) ? (int) $selectedId : null;
        $selectedOrder = $selectedOrderId === null
            ? null
            : $orders->first(fn (PurchaseOrder $order): bool => (int) $order->getKey() === $selectedOrderId);

        /** @var Collection<int, mixed> $existingLines */
        $existingLines = $receipt?->lines?->keyBy('purchase_order_line_id') ?? collect();

        return view('goods-receipts.form', [
            'receipt' => $receipt,
            'orders' => $orders,
            'selectedOrder' => $selectedOrder,
            'existingLines' => $existingLines,
            'warehouses' => Warehouse::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->with(['locations' => fn ($query) => $query->where('is_active', true)->orderBy('code')])
                ->orderBy('code')
                ->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function rules(bool $includeSeries = true): array
    {
        $rules = [
            'purchase_order_id' => ['required', 'integer'],
            'receipt_date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.purchase_order_line_id' => ['required', 'integer'],
            'lines.*.warehouse_id' => ['required', 'integer'],
            'lines.*.location_id' => ['required', 'integer'],
            'lines.*.received_quantity' => ['required', 'decimal:0,6', 'min:0'],
            'lines.*.accepted_quantity' => ['required', 'decimal:0,6', 'min:0'],
            'lines.*.pending_quantity' => ['required', 'decimal:0,6', 'min:0'],
            'lines.*.rejected_quantity' => ['required', 'decimal:0,6', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:1000'],
        ];
        if ($includeSeries) {
            $rules['series_code'] = ['nullable', 'string', 'max:64'];
        }

        return $rules;
    }

    /** @param array<string, mixed> $validated */
    private function draftData(array $validated): GoodsReceiptDraftData
    {
        $lines = [];
        foreach ($validated['lines'] as $line) {
            $lines[] = new GoodsReceiptLineData(
                purchaseOrderLineId: (int) $line['purchase_order_line_id'],
                warehouseId: (int) $line['warehouse_id'],
                locationId: (int) $line['location_id'],
                receivedQuantity: (string) $line['received_quantity'],
                acceptedQuantity: (string) $line['accepted_quantity'],
                pendingQuantity: (string) $line['pending_quantity'],
                rejectedQuantity: (string) $line['rejected_quantity'],
                note: isset($line['note']) ? (string) $line['note'] : null,
            );
        }

        return new GoodsReceiptDraftData(
            purchaseOrderId: (int) $validated['purchase_order_id'],
            receiptDate: (string) $validated['receipt_date'],
            note: isset($validated['note']) ? (string) $validated['note'] : null,
            lines: $lines,
        );
    }

    private function receipt(int $goodsReceiptId): GoodsReceipt
    {
        return GoodsReceipt::query()
            ->where('company_id', $this->companyId())
            ->whereKey($goodsReceiptId)
            ->firstOrFail();
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
