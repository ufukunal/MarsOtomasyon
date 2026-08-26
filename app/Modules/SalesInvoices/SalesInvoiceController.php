<?php

namespace App\Modules\SalesInvoices;

use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Dispatches\Enums\DispatchStatus;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Product;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\SalesInvoices\Actions\CreateSalesInvoice;
use App\Modules\SalesInvoices\Actions\SalesInvoiceDraftData;
use App\Modules\SalesInvoices\Actions\SalesInvoiceLineData;
use App\Modules\SalesInvoices\Enums\SalesInvoiceMode;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final readonly class SalesInvoiceController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CreateSalesInvoice $createInvoice,
    ) {}

    public function index(Request $request): View
    {
        $companyId = $this->companyId();
        $search = trim((string) $request->query('q', ''));
        $query = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->with(['account', 'sourceSalesOrder', 'sourceDispatch']);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->whereRaw('number ILIKE ?', [$like])
                    ->orWhereRaw('customer_legal_name ILIKE ?', [$like])
                    ->orWhereHas('sourceSalesOrder', fn ($order) => $order->whereRaw('number ILIKE ?', [$like]))
                    ->orWhereHas('sourceDispatch', fn ($dispatch) => $dispatch->whereRaw('number ILIKE ?', [$like]));
            });
        }

        return view('sales-invoices.index', [
            'invoices' => $query->orderByDesc('invoice_date')->orderByDesc('id')->paginate(50)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        $companyId = $this->companyId();
        $mode = SalesInvoiceMode::tryFrom((string) $request->query('mode', 'direct')) ?? SalesInvoiceMode::Direct;
        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->where('status', AccountStatus::Active->value)
            ->whereIn('type', [AccountType::Customer->value, AccountType::Mixed->value])
            ->orderBy('code')
            ->get();
        $accountIds = $accounts->pluck('id');

        return view('sales-invoices.create', [
            'mode' => $mode,
            'modes' => SalesInvoiceMode::cases(),
            'accounts' => $accounts,
            'billingAddresses' => AccountAddress::query()
                ->where('company_id', $companyId)
                ->whereIn('account_id', $accountIds)
                ->where('type', AccountAddressType::Billing->value)
                ->orderByDesc('is_default')
                ->orderBy('label')
                ->get(),
            'orders' => SalesOrder::query()
                ->where('company_id', $companyId)
                ->with(['account', 'lines'])
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
            'dispatches' => Dispatch::query()
                ->where('company_id', $companyId)
                ->where('status', DispatchStatus::Finalized->value)
                ->with(['account', 'salesOrder', 'lines'])
                ->orderByDesc('dispatch_date')
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
            'products' => Product::query()
                ->where('company_id', $companyId)
                ->where('status', ProductStatus::Active->value)
                ->with('tax')
                ->orderBy('code')
                ->limit(500)
                ->get(),
            'warehouses' => Warehouse::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->with(['locations' => fn ($query) => $query->where('is_active', true)->orderBy('code')])
                ->orderBy('code')
                ->get(),
            'zeroReasons' => TaxZeroReason::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
            'priceBases' => PriceBasis::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rawLines = $request->input('lines', []);
        if (is_array($rawLines)) {
            $request->merge([
                'lines' => array_values(array_filter(
                    $rawLines,
                    static fn ($line): bool => is_array($line) && collect($line)->contains(
                        static fn ($value): bool => $value !== null && $value !== '',
                    ),
                )),
            ]);
        }

        $validated = $request->validate([
            'series_code' => ['nullable', 'string', 'max:64'],
            'mode' => ['required', Rule::enum(SalesInvoiceMode::class)],
            'account_id' => ['nullable', 'integer'],
            'sales_order_id' => ['nullable', 'integer'],
            'dispatch_id' => ['nullable', 'integer'],
            'source_billing_address_id' => ['required', 'integer'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'document_discount_rate' => ['nullable', 'decimal:0,6', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['nullable', 'integer'],
            'lines.*.sales_order_line_id' => ['nullable', 'integer'],
            'lines.*.dispatch_line_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'lines.*.allocation_key' => ['nullable', 'string', 'max:64', 'regex:/^[1-9][0-9]*:[1-9][0-9]*$/D'],
            'lines.*.unit_price' => ['nullable', 'decimal:0,6', 'min:0'],
            'lines.*.price_basis' => ['nullable', Rule::enum(PriceBasis::class)],
            'lines.*.line_discount_rate' => ['nullable', 'decimal:0,6', 'min:0', 'max:100'],
            'lines.*.tax_is_zeroed' => ['sometimes', 'boolean'],
            'lines.*.tax_zero_reason_id' => ['nullable', 'integer'],
        ]);

        $lines = [];
        foreach ($validated['lines'] as $line) {
            [$warehouseId, $locationId] = $this->allocationPair($line['allocation_key'] ?? null);
            $lines[] = new SalesInvoiceLineData(
                quantity: (string) $line['quantity'],
                productId: isset($line['product_id']) ? (int) $line['product_id'] : null,
                salesOrderLineId: isset($line['sales_order_line_id']) ? (int) $line['sales_order_line_id'] : null,
                dispatchLineId: isset($line['dispatch_line_id']) ? (int) $line['dispatch_line_id'] : null,
                warehouseId: $warehouseId,
                locationId: $locationId,
                unitPrice: isset($line['unit_price']) ? (string) $line['unit_price'] : null,
                priceBasis: isset($line['price_basis']) ? PriceBasis::from((string) $line['price_basis']) : null,
                lineDiscountRate: isset($line['line_discount_rate']) ? (string) $line['line_discount_rate'] : null,
                taxIsZeroed: (bool) ($line['tax_is_zeroed'] ?? false),
                taxZeroReasonId: isset($line['tax_zero_reason_id']) ? (int) $line['tax_zero_reason_id'] : null,
            );
        }

        $invoice = $this->createInvoice->handle(new SalesInvoiceDraftData(
            mode: SalesInvoiceMode::from((string) $validated['mode']),
            sourceBillingAddressId: (int) $validated['source_billing_address_id'],
            invoiceDate: (string) $validated['invoice_date'],
            lines: $lines,
            accountId: isset($validated['account_id']) ? (int) $validated['account_id'] : null,
            salesOrderId: isset($validated['sales_order_id']) ? (int) $validated['sales_order_id'] : null,
            dispatchId: isset($validated['dispatch_id']) ? (int) $validated['dispatch_id'] : null,
            documentDiscountRate: isset($validated['document_discount_rate']) ? (string) $validated['document_discount_rate'] : null,
            note: isset($validated['note']) ? (string) $validated['note'] : null,
        ), (string) ($validated['series_code'] ?? 'default'));

        return redirect()->route('sales-invoices.show', $invoice->getKey())
            ->with('status', 'Taslak satış faturası oluşturuldu.');
    }

    public function show(int $salesInvoice): View
    {
        $record = SalesInvoice::query()
            ->where('company_id', $this->companyId())
            ->whereKey($salesInvoice)
            ->with([
                'account', 'sourceBillingAddress', 'sourceSalesOrder', 'sourceDispatch',
                'lines.warehouse', 'lines.location', 'lines.tax', 'lines.taxZeroReason',
            ])
            ->firstOrFail();

        return view('sales-invoices.show', ['invoice' => $record]);
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
