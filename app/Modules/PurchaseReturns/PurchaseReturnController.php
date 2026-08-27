<?php

namespace App\Modules\PurchaseReturns;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\GoodsReceipts\Models\GoodsReceiptLine;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\PurchaseReturns\Actions\CreatePurchaseReturn;
use App\Modules\PurchaseReturns\Actions\FinalizePurchaseReturn;
use App\Modules\PurchaseReturns\Actions\PurchaseReturnDraftData;
use App\Modules\PurchaseReturns\Actions\PurchaseReturnLineData;
use App\Modules\PurchaseReturns\Models\PurchaseReturn;
use App\Modules\SupplierInvoices\Models\SupplierInvoiceLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final readonly class PurchaseReturnController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CreatePurchaseReturn $createPurchaseReturn,
        private FinalizePurchaseReturn $finalizePurchaseReturn,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $query = PurchaseReturn::query()
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

        return view('purchase-returns.index', [
            'returns' => $query->orderByDesc('return_date')->orderByDesc('id')->paginate(50)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        $companyId = $this->companyId();
        $orders = PurchaseOrder::query()
            ->with('account')
            ->where('company_id', $companyId)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();
        $selectedId = $request->old('purchase_order_id', $request->query('purchase_order_id'));
        $selectedOrderId = is_numeric($selectedId) ? (int) $selectedId : null;
        $selectedOrder = $selectedOrderId === null
            ? null
            : $orders->first(fn (PurchaseOrder $order): bool => (int) $order->getKey() === $selectedOrderId);

        /** @var Collection<int, GoodsReceiptLine> $receiptLines */
        $receiptLines = collect();
        /** @var Collection<int, SupplierInvoiceLine> $invoiceLines */
        $invoiceLines = collect();
        $acceptedByReceiptLine = collect();

        if ($selectedOrder instanceof PurchaseOrder) {
            $receiptLines = GoodsReceiptLine::query()
                ->with('goodsReceipt')
                ->where('company_id', $companyId)
                ->where('purchase_order_id', $selectedOrder->getKey())
                ->whereHas('goodsReceipt', fn ($query) => $query->where('status', 'finalized'))
                ->orderBy('goods_receipt_id')
                ->orderBy('position')
                ->get();
            $invoiceLines = SupplierInvoiceLine::query()
                ->with('supplierInvoice')
                ->where('company_id', $companyId)
                ->where('purchase_order_id', $selectedOrder->getKey())
                ->whereHas('supplierInvoice', fn ($query) => $query->where('status', 'finalized'))
                ->orderBy('supplier_invoice_id')
                ->orderBy('position')
                ->get();
            $acceptedByReceiptLine = collect(

                DB::table('goods_receipt_line_quality')
                    ->where('company_id', $companyId)
                    ->whereIn('goods_receipt_line_id', $receiptLines->modelKeys())
                    ->pluck('accepted_quantity', 'goods_receipt_line_id')
                    ->all(),
            );
        }

        return view('purchase-returns.form', compact(
            'orders',
            'selectedOrder',
            'receiptLines',
            'invoiceLines',
            'acceptedByReceiptLine',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'series_code' => ['nullable', 'string', 'max:64'],
            'purchase_order_id' => ['required', 'integer'],
            'return_date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.goods_receipt_line_id' => ['required', 'integer'],
            'lines.*.supplier_invoice_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'decimal:0,6', 'min:0'],
        ]);

        $lines = [];
        foreach ($validated['lines'] as $line) {
            $quantity = trim((string) $line['quantity']);
            if (preg_match('/^0+(?:\.0+)?$/D', $quantity) === 1) {
                continue;
            }
            $lines[] = new PurchaseReturnLineData(
                goodsReceiptLineId: (int) $line['goods_receipt_line_id'],
                supplierInvoiceLineId: (int) $line['supplier_invoice_line_id'],
                quantity: $quantity,
            );
        }

        $purchaseReturn = $this->createPurchaseReturn->handle(
            new PurchaseReturnDraftData(
                purchaseOrderId: (int) $validated['purchase_order_id'],
                returnDate: (string) $validated['return_date'],
                note: isset($validated['note']) ? (string) $validated['note'] : null,
                lines: $lines,
            ),
            (string) ($validated['series_code'] ?? 'default'),
        );

        return redirect()->route('purchase-returns.show', $purchaseReturn->getKey())
            ->with('status', 'Satınalma iadesi taslağı oluşturuldu.');
    }

    public function show(int $purchaseReturn): View
    {
        $return = $this->purchaseReturn($purchaseReturn)->load([
            'account', 'purchaseOrder', 'lines.goodsReceiptLine.goodsReceipt', 'lines.supplierInvoiceLine.supplierInvoice',
        ]);

        return view('purchase-returns.show', ['return' => $return]);
    }

    public function finalize(int $purchaseReturn): RedirectResponse
    {
        $return = $this->finalizePurchaseReturn->handle($purchaseReturn);

        return redirect()->route('purchase-returns.show', $return->getKey())
            ->with('status', 'Satınalma iadesi kesinleştirildi; stok çıkışı ve tedarikçi cari düzeltmesi işlendi.');
    }

    private function purchaseReturn(int $purchaseReturnId): PurchaseReturn
    {
        return PurchaseReturn::query()
            ->where('company_id', $this->companyId())
            ->whereKey($purchaseReturnId)
            ->firstOrFail();
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
