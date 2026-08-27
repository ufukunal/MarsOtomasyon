<?php

namespace App\Modules\SupplierInvoices;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\SupplierInvoices\Actions\CreateSupplierInvoice;
use App\Modules\SupplierInvoices\Actions\FinalizeSupplierInvoice;
use App\Modules\SupplierInvoices\Actions\SupplierInvoiceDraftData;
use App\Modules\SupplierInvoices\Actions\SupplierInvoiceLineData;
use App\Modules\SupplierInvoices\Actions\UpdateSupplierInvoice;
use App\Modules\SupplierInvoices\Models\SupplierInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final readonly class SupplierInvoiceController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CreateSupplierInvoice $createSupplierInvoice,
        private UpdateSupplierInvoice $updateSupplierInvoice,
        private FinalizeSupplierInvoice $finalizeSupplierInvoice,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $query = SupplierInvoice::query()
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

        return view('supplier-invoices.index', [
            'invoices' => $query->orderByDesc('invoice_date')->orderByDesc('id')->paginate(50)->withQueryString(),
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
        $invoice = $this->createSupplierInvoice->handle(
            $this->draftData($validated),
            (string) ($validated['series_code'] ?? 'default'),
        );

        return redirect()->route('supplier-invoices.show', $invoice->getKey())
            ->with('status', 'Alış faturası taslağı oluşturuldu.');
    }

    public function show(int $supplierInvoice): View
    {
        $invoice = $this->invoice($supplierInvoice)->load([
            'account', 'purchaseOrder', 'lines.purchaseOrderLine.progress',
        ]);

        return view('supplier-invoices.show', ['invoice' => $invoice]);
    }

    public function edit(Request $request, int $supplierInvoice): View
    {
        $invoice = $this->invoice($supplierInvoice)->load('lines');
        if (! $invoice->isDraft()) {
            abort(409, 'Kesinleşmiş alış faturası düzenlenemez.');
        }

        return $this->form($request, $invoice);
    }

    public function update(Request $request, int $supplierInvoice): RedirectResponse
    {
        $validated = $request->validate($this->rules(includeSeries: false));
        $invoice = $this->updateSupplierInvoice->handle($supplierInvoice, $this->draftData($validated));

        return redirect()->route('supplier-invoices.show', $invoice->getKey())
            ->with('status', 'Alış faturası taslağı güncellendi.');
    }

    public function finalize(int $supplierInvoice): RedirectResponse
    {
        $invoice = $this->finalizeSupplierInvoice->handle($supplierInvoice);

        return redirect()->route('supplier-invoices.show', $invoice->getKey())
            ->with('status', 'Alış faturası kesinleştirildi; cari ve satınalma faturalama progress ledgerlarına işlendi.');
    }

    private function form(Request $request, ?SupplierInvoice $invoice): View
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
            $selectedId = $invoice->purchase_order_id ?? $request->query('purchase_order_id');
        }
        $selectedOrderId = is_numeric($selectedId) ? (int) $selectedId : null;
        $selectedOrder = $selectedOrderId === null
            ? null
            : $orders->first(fn (PurchaseOrder $order): bool => (int) $order->getKey() === $selectedOrderId);

        /** @var Collection<int, mixed> $existingLines */
        $existingLines = $invoice?->lines?->keyBy('purchase_order_line_id') ?? collect();

        return view('supplier-invoices.form', [
            'invoice' => $invoice,
            'orders' => $orders,
            'selectedOrder' => $selectedOrder,
            'existingLines' => $existingLines,
        ]);
    }

    /** @return array<string, mixed> */
    private function rules(bool $includeSeries = true): array
    {
        $rules = [
            'purchase_order_id' => ['required', 'integer'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.purchase_order_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'decimal:0,6', 'min:0'],
        ];
        if ($includeSeries) {
            $rules['series_code'] = ['nullable', 'string', 'max:64'];
        }

        return $rules;
    }

    /** @param array<string, mixed> $validated */
    private function draftData(array $validated): SupplierInvoiceDraftData
    {
        $lines = [];
        foreach ($validated['lines'] as $line) {
            $quantity = trim((string) $line['quantity']);
            if (preg_match('/^0+(?:\.0+)?$/D', $quantity) === 1) {
                continue;
            }

            $lines[] = new SupplierInvoiceLineData(
                purchaseOrderLineId: (int) $line['purchase_order_line_id'],
                quantity: $quantity,
            );
        }

        return new SupplierInvoiceDraftData(
            purchaseOrderId: (int) $validated['purchase_order_id'],
            invoiceDate: (string) $validated['invoice_date'],
            note: isset($validated['note']) ? (string) $validated['note'] : null,
            lines: $lines,
        );
    }

    private function invoice(int $supplierInvoiceId): SupplierInvoice
    {
        return SupplierInvoice::query()
            ->where('company_id', $this->companyId())
            ->whereKey($supplierInvoiceId)
            ->firstOrFail();
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
