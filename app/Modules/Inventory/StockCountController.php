<?php

namespace App\Modules\Inventory;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Inventory\Counts\StockCountService;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use LogicException;

final readonly class StockCountController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private StockCountService $stockCountService,
    ) {}

    public function index(): View
    {
        $counts = StockCount::query()
            ->where('company_id', $this->companyId())
            ->with(['warehouse', 'location'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(30);

        return view('inventory.counts.index', ['counts' => $counts]);
    }

    public function create(): View
    {
        $locations = WarehouseLocation::query()
            ->where('company_id', $this->companyId())
            ->where('is_active', true)
            ->whereHas('warehouse', function (Builder $query): void {
                $query->where('is_active', true);
            })
            ->with('warehouse')
            ->orderBy('warehouse_id')
            ->orderBy('code')
            ->get();

        return view('inventory.counts.create', [
            'locations' => $locations,
            'operationKey' => (string) Str::ulid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'operation_key' => ['required', 'string', 'max:64'],
            'location_id' => ['required', 'integer', 'min:1'],
        ]);
        $count = DB::transaction(fn (): StockCount => $this->stockCountService->start(
            $this->companyId(),
            (int) $validated['location_id'],
            (string) $validated['operation_key'],
        ));

        return redirect()->route('inventory.counts.show', $count->getKey())
            ->with('status', 'Stok sayımı başlatıldı.');
    }

    public function show(int $count): View
    {
        $stockCount = StockCount::query()
            ->where('company_id', $this->companyId())
            ->with(['warehouse', 'location', 'lines.product', 'lines.adjustmentMovement'])
            ->findOrFail($count);
        $products = Product::query()
            ->where('company_id', $this->companyId())
            ->where('status', ProductStatus::Active->value)
            ->orderBy('name')
            ->orderBy('code')
            ->get();

        return view('inventory.counts.show', [
            'stockCount' => $stockCount,
            'products' => $products,
        ]);
    }

    public function setLine(Request $request, int $count): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'counted_quantity' => ['required', 'decimal:0,6', 'gte:0'],
            'valuation_unit_cost' => ['nullable', 'decimal:0,6', 'gt:0'],
        ]);

        DB::transaction(fn () => $this->stockCountService->setCounted(
            $this->companyId(),
            $count,
            (int) $validated['product_id'],
            (string) $validated['counted_quantity'],
            isset($validated['valuation_unit_cost']) ? (string) $validated['valuation_unit_cost'] : null,
        ));

        return redirect()->route('inventory.counts.show', $count)
            ->with('status', 'Sayım satırı güncellendi.');
    }

    public function scan(Request $request, int $count): RedirectResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:128'],
            'quantity' => ['nullable', 'decimal:0,6', 'gt:0'],
        ]);

        $line = DB::transaction(fn () => $this->stockCountService->scanBarcode(
            $this->companyId(),
            $count,
            (string) $validated['barcode'],
            isset($validated['quantity']) ? (string) $validated['quantity'] : '1',
        ));

        return redirect()->route('inventory.counts.show', $count)
            ->with('status', 'Barkod sayıldı: '.$line->product_id.' / +'.(isset($validated['quantity']) ? (string) $validated['quantity'] : '1'));
    }

    public function post(int $count): RedirectResponse
    {
        $stockCount = DB::transaction(fn (): StockCount => $this->stockCountService->post(
            $this->companyId(),
            $count,
        ));

        return redirect()->route('inventory.counts.show', $stockCount->getKey())
            ->with('status', 'Stok sayımı finalize edildi ve fark hareketleri işlendi.');
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();

        return is_int($companyId)
            ? $companyId
            : throw new LogicException('Stock count workspace requires a persisted active company.');
    }
}
