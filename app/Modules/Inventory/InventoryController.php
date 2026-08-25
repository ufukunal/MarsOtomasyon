<?php

namespace App\Modules\Inventory;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Inventory\Actions\PostManualStockMovement;
use App\Modules\Inventory\Enums\ManualStockMovementKind;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;

final readonly class InventoryController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
        private PostManualStockMovement $postMovement,
    ) {}

    public function stock(): View
    {
        $balances = StockBalance::query()
            ->where('company_id', $this->companyId())
            ->with(['product', 'warehouse', 'location'])
            ->where('quantity', '>', 0)
            ->orderBy('warehouse_id')
            ->orderBy('location_id')
            ->orderBy('product_id')
            ->paginate(50);

        return view('inventory.stock.index', ['balances' => $balances]);
    }

    public function movements(): View
    {
        $movements = StockMovement::query()
            ->where('company_id', $this->companyId())
            ->with(['product', 'warehouse', 'location'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(50);

        return view('inventory.stock.movements', ['movements' => $movements]);
    }

    public function createMovement(): View
    {
        return view('inventory.stock.movement-form', [
            'operationKey' => (string) Str::ulid(),
            'movementKinds' => ManualStockMovementKind::cases(),
            'products' => Product::query()
                ->where('company_id', $this->companyId())
                ->where('status', ProductStatus::Active->value)
                ->orderBy('name')
                ->orderBy('code')
                ->get(),
            'warehouses' => Warehouse::query()
                ->where('company_id', $this->companyId())
                ->where('is_active', true)
                ->with(['locations' => fn ($query) => $query->where('is_active', true)->orderBy('code')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'operation_key' => ['required', 'string', 'max:64'],
            'product_id' => ['required', 'integer', 'min:1'],
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'location_id' => ['required', 'integer', 'min:1'],
            'movement_type' => ['required', Rule::enum(ManualStockMovementKind::class)],
            'quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'unit_cost' => ['nullable', 'decimal:0,6', 'gte:0'],
            'note' => ['nullable', 'string', 'max:240'],
        ]);

        $movement = $this->postMovement->handle(
            operationKey: (string) $validated['operation_key'],
            productId: (int) $validated['product_id'],
            warehouseId: (int) $validated['warehouse_id'],
            locationId: (int) $validated['location_id'],
            kind: ManualStockMovementKind::from((string) $validated['movement_type']),
            quantity: (string) $validated['quantity'],
            unitCost: isset($validated['unit_cost']) ? (string) $validated['unit_cost'] : null,
            note: isset($validated['note']) ? (string) $validated['note'] : null,
        );

        return redirect()->route('inventory.stock.movements')
            ->with('status', 'Stok hareketi işlendi: #'.$movement->getKey());
    }

    public function warehouses(): View
    {
        $warehouses = Warehouse::query()
            ->where('company_id', $this->companyId())
            ->with(['locations' => fn ($query) => $query->orderBy('code')])
            ->orderByRaw('CASE WHEN is_active THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get();

        return view('inventory.warehouses.index', ['warehouses' => $warehouses]);
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:160'],
        ]);
        $companyId = $this->companyId();

        DB::transaction(function () use ($validated, $companyId): void {
            $warehouse = Warehouse::query()->create([
                'company_id' => $companyId,
                'code' => mb_strtoupper(trim((string) $validated['code'])),
                'name' => trim((string) $validated['name']),
                'is_active' => true,
            ]);

            $this->audit->record(
                AuditAction::WarehouseCreated,
                AuditTargetType::Warehouse,
                $warehouse->getKey(),
                after: [
                    'code' => (string) $warehouse->code,
                    'name' => (string) $warehouse->name,
                    'is_active' => true,
                ],
            );
        });

        return redirect()->route('inventory.warehouses.index')
            ->with('status', 'Depo oluşturuldu.');
    }

    public function storeLocation(Request $request, int $warehouse): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:160'],
        ]);
        $companyId = $this->companyId();

        DB::transaction(function () use ($validated, $companyId, $warehouse): void {
            $warehouseModel = Warehouse::query()
                ->where('company_id', $companyId)
                ->findOrFail($warehouse);

            $location = WarehouseLocation::query()->create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseModel->getKey(),
                'code' => mb_strtoupper(trim((string) $validated['code'])),
                'name' => trim((string) $validated['name']),
                'is_active' => true,
            ]);

            $this->audit->record(
                AuditAction::WarehouseLocationCreated,
                AuditTargetType::WarehouseLocation,
                $location->getKey(),
                after: [
                    'warehouse_id' => $warehouseModel->getKey(),
                    'code' => (string) $location->code,
                    'name' => (string) $location->name,
                    'is_active' => true,
                ],
            );
        });

        return redirect()->route('inventory.warehouses.index')
            ->with('status', 'Depo lokasyonu oluşturuldu.');
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();

        return is_int($companyId)
            ? $companyId
            : throw new LogicException('Inventory workspace requires a persisted active company.');
    }
}
