<?php

namespace App\Modules\SalesOrders;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Search\ProductSearchQuery;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\SalesOrders\Actions\CreateSalesOrder;
use App\Modules\SalesOrders\Actions\SalesOrderDraftData;
use App\Modules\SalesOrders\Actions\SalesOrderLineData;
use App\Modules\SalesOrders\Actions\UpdateSalesOrder;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;

final readonly class SalesOrderController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CreateSalesOrder $createSalesOrder,
        private UpdateSalesOrder $updateSalesOrder,
        private ProductSearchQuery $productSearchQuery,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $source = (string) $request->query('source', 'all');
        if (! in_array($source, ['all', 'manual', 'quote'], true)) {
            $source = 'all';
        }

        $query = SalesOrder::query()->with('account')->where('company_id', $this->companyId());
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->whereRaw('number ILIKE ?', [$like])
                    ->orWhereHas('account', fn ($account) => $account->whereRaw('legal_name ILIKE ?', [$like]));
            });
        }
        if ($source === 'manual') {
            $query->whereNull('source_quote_id');
        } elseif ($source === 'quote') {
            $query->whereNotNull('source_quote_id');
        }

        return view('sales-orders.index', [
            'orders' => $query->orderByDesc('order_date')->orderByDesc('id')->paginate(50)->withQueryString(),
            'search' => $search,
            'sourceFilter' => $source,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, null);
    }

    public function productSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:128'],
        ]);
        $search = trim((string) $validated['q']);
        if ($search === '') {
            return response()->json(['data' => []]);
        }

        $companyId = $this->companyId();
        $products = $this->productSearchQuery
            ->build($companyId, $search, ProductStatus::Active)
            ->whereHas('tax', function (Builder $query) use ($companyId): void {
                $query->where('company_id', $companyId)->where('is_active', true);
            })
            ->with('tax')
            ->orderBy('name')
            ->orderBy('code')
            ->limit(20)
            ->get();

        $data = $products->map(static function (Product $product): array {
            $tax = $product->tax;
            if (! $tax instanceof Tax) {
                throw new LogicException('Order product search requires a persisted tax relation.');
            }

            return [
                'id' => (int) $product->getKey(),
                'code' => (string) $product->code,
                'name' => (string) $product->name,
                'label' => (string) $product->code.' — '.(string) $product->name,
                'sale_price_net' => (string) $product->sale_price_net,
                'tax_id' => (int) $product->tax_id,
                'tax_code' => (string) $tax->code,
                'tax_rate' => (string) $tax->rate,
            ];
        })->values()->all();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $order = $this->createSalesOrder->handle(
            $this->draftData($validated),
            (string) ($validated['series_code'] ?? 'default'),
        );

        return redirect()->route('sales-orders.show', $order->getKey())->with('status', 'Satış siparişi oluşturuldu.');
    }

    public function show(int $salesOrder): View
    {
        $order = $this->salesOrder($salesOrder)->load([
            'account',
            'lines.product',
            'lines.tax',
            'lines.taxZeroReason',
            'sourceQuote',
            'sourceRevision',
        ]);

        return view('sales-orders.show', ['order' => $order]);
    }

    public function edit(Request $request, int $salesOrder): View
    {
        $order = $this->salesOrder($salesOrder)->load('lines');
        if (! $order->isDraft() || ! $order->isManual()) {
            abort(409, 'Yalnız manuel oluşturulmuş taslak siparişler düzenlenebilir.');
        }

        return $this->form($request, $order);
    }

    public function update(Request $request, int $salesOrder): RedirectResponse
    {
        $this->salesOrder($salesOrder);
        $validated = $request->validate($this->rules(includeSeries: false));
        $updated = $this->updateSalesOrder->handle($salesOrder, $this->draftData($validated));

        return redirect()->route('sales-orders.show', $updated->getKey())->with('status', 'Satış siparişi güncellendi.');
    }

    private function form(Request $request, ?SalesOrder $order): View
    {
        $companyId = $this->companyId();

        return view('sales-orders.form', [
            'order' => $order,
            'accounts' => Account::query()
                ->where('company_id', $companyId)->where('status', 'active')
                ->whereIn('type', ['customer', 'mixed'])->orderBy('legal_name')->get(),
            'selectedProductLabels' => $this->selectedProductLabels($request, $order),
            'zeroReasons' => TaxZeroReason::query()
                ->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
            'priceBases' => PriceBasis::cases(),
        ]);
    }

    /** @return array<int, string> */
    private function selectedProductLabels(Request $request, ?SalesOrder $order): array
    {
        $productIds = [];
        $oldLines = $request->old('lines');
        if (is_array($oldLines)) {
            foreach ($oldLines as $line) {
                if (is_array($line) && isset($line['product_id']) && is_numeric($line['product_id'])) {
                    $productIds[] = (int) $line['product_id'];
                }
            }
        } elseif ($order !== null) {
            foreach ($order->lines as $line) {
                $productIds[] = (int) $line->product_id;
            }
        }

        $productIds = array_values(array_unique(array_filter($productIds, static fn (int $id): bool => $id > 0)));
        if ($productIds === []) {
            return [];
        }

        $labels = [];
        $products = Product::query()
            ->where('company_id', $this->companyId())
            ->whereIn('id', $productIds)
            ->get(['id', 'code', 'name']);
        foreach ($products as $product) {
            $labels[(int) $product->getKey()] = (string) $product->code.' — '.(string) $product->name;
        }

        return $labels;
    }

    /** @return array<string, mixed> */
    private function rules(bool $includeSeries = true): array
    {
        $rules = [
            'account_id' => ['required', 'integer'],
            'order_date' => ['required', 'date_format:Y-m-d'],
            'currency_code' => ['required', 'string', 'size:3'],
            'document_discount_rate' => ['required', 'decimal:0,6', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.quantity' => ['required', 'decimal:0,6'],
            'lines.*.unit_price' => ['required', 'decimal:0,6'],
            'lines.*.price_basis' => ['required', Rule::enum(PriceBasis::class)],
            'lines.*.line_discount_rate' => ['required', 'decimal:0,6', 'min:0', 'max:100'],
            'lines.*.tax_zero_reason_id' => ['nullable', 'integer'],
        ];
        if ($includeSeries) {
            $rules['series_code'] = ['nullable', 'string', 'max:64'];
        }

        return $rules;
    }

    /** @param array<string, mixed> $validated */
    private function draftData(array $validated): SalesOrderDraftData
    {
        $lines = [];
        foreach ($validated['lines'] as $line) {
            $lines[] = new SalesOrderLineData(
                productId: (int) $line['product_id'],
                quantity: (string) $line['quantity'],
                unitPrice: (string) $line['unit_price'],
                priceBasis: PriceBasis::from((string) $line['price_basis']),
                lineDiscountRate: (string) $line['line_discount_rate'],
                taxZeroReasonId: isset($line['tax_zero_reason_id']) && $line['tax_zero_reason_id'] !== '' ? (int) $line['tax_zero_reason_id'] : null,
                description: isset($line['description']) ? (string) $line['description'] : null,
            );
        }

        return new SalesOrderDraftData(
            accountId: (int) $validated['account_id'],
            orderDate: (string) $validated['order_date'],
            currencyCode: (string) $validated['currency_code'],
            documentDiscountRate: (string) $validated['document_discount_rate'],
            note: isset($validated['note']) ? (string) $validated['note'] : null,
            lines: $lines,
        );
    }

    private function salesOrder(int $salesOrderId): SalesOrder
    {
        return SalesOrder::query()->where('company_id', $this->companyId())->whereKey($salesOrderId)->firstOrFail();
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
