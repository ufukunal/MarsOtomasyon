<?php

namespace App\Modules\Production;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Production\Actions\ProductionOperations;
use App\Modules\Production\Files\ProductionFileManager;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionRecipe;
use App\Modules\Products\Models\Product;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ProductionController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private ProductionOperations $operations,
        private ProductionFileManager $files,
    ) {}

    public function index(): View
    {
        $companyId = $this->companyId();

        return view('production.index', [
            'recipes' => ProductionRecipe::query()
                ->where('company_id', $companyId)
                ->with('product')
                ->orderByDesc('is_active')
                ->orderBy('code')
                ->get(),
            'orders' => ProductionOrder::query()
                ->where('company_id', $companyId)
                ->with(['product', 'warehouse'])
                ->orderByDesc('id')
                ->paginate(50),
            'products' => Product::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'locations' => WarehouseLocation::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('warehouse_id')
                ->orderBy('code')
                ->get(['id', 'warehouse_id', 'code', 'name']),
        ]);
    }

    public function show(int $order): View
    {
        $model = $this->order($order);
        $model->load([
            'recipe',
            'product',
            'warehouse',
            'location',
            'materials.product',
            'losses.product',
            'events',
        ]);

        return view('production.show', [
            'order' => $model,
            'attachments' => $this->files->all($order),
        ]);
    }

    public function report(): View
    {
        $companyId = $this->companyId();
        $rows = DB::table('production_orders as production')
            ->join('products as product', function ($join): void {
                $join->on('product.company_id', '=', 'production.company_id')
                    ->on('product.id', '=', 'production.product_id');
            })
            ->join('warehouses as warehouse', function ($join): void {
                $join->on('warehouse.company_id', '=', 'production.company_id')
                    ->on('warehouse.id', '=', 'production.warehouse_id');
            })
            ->where('production.company_id', $companyId)
            ->orderByDesc('production.id')
            ->limit(500)
            ->get([
                'production.id',
                'production.order_no',
                'production.status',
                'production.planned_quantity',
                'production.material_cost',
                'production.loss_cost',
                'production.output_quantity',
                'production.output_unit_cost',
                'production.output_value',
                'production.completed_at',
                'product.code as product_code',
                'product.name as product_name',
                'warehouse.code as warehouse_code',
                'warehouse.name as warehouse_name',
            ]);

        return view('production.report', ['rows' => $rows]);
    }

    public function storeRecipe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:160'],
            'output_quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'note' => ['nullable', 'string', 'max:500'],
            'materials' => ['required', 'array', 'min:1', 'max:10'],
            'materials.*.product_id' => ['nullable', 'integer', 'min:1'],
            'materials.*.quantity' => ['nullable', 'decimal:0,6', 'gt:0'],
        ]);

        $materials = [];
        foreach ($data['materials'] as $material) {
            $productId = $material['product_id'] ?? null;
            $quantity = $material['quantity'] ?? null;
            if ($productId === null && $quantity === null) {
                continue;
            }
            if ($productId === null || $quantity === null) {
                throw ValidationException::withMessages(['materials' => 'Malzeme ürünü ve miktarı birlikte girilmelidir.']);
            }
            $materials[] = ['product_id' => (int) $productId, 'quantity' => (string) $quantity];
        }
        if ($materials === []) {
            throw ValidationException::withMessages(['materials' => 'En az bir malzeme satırı zorunludur.']);
        }

        $recipe = $this->perform(fn () => $this->operations->createRecipe(
            $this->companyId(),
            (int) $data['product_id'],
            (string) $data['code'],
            (string) $data['name'],
            (string) $data['output_quantity'],
            $materials,
            $this->nullableString($data['note'] ?? null),
        ));

        return redirect()->route('production.index')->with('status', 'Üretim reçetesi oluşturuldu: '.$recipe->code);
    }

    public function storeOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipe_id' => ['required', 'integer', 'min:1'],
            'order_no' => ['required', 'string', 'max:64'],
            'planned_quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'location_id' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->perform(fn () => $this->operations->createOrder(
            $this->companyId(),
            (int) $data['recipe_id'],
            (string) $data['order_no'],
            (string) $data['planned_quantity'],
            (int) $data['warehouse_id'],
            (int) $data['location_id'],
            $this->nullableString($data['note'] ?? null),
        ));

        return redirect()->route('production.show', $order->getKey())->with('status', 'Üretim emri oluşturuldu.');
    }

    public function issueMaterials(int $order): RedirectResponse
    {
        $this->perform(fn () => $this->operations->issueMaterials($this->companyId(), $order));

        return $this->back($order, 'Malzeme çıkışı tamamlandı.');
    }

    public function recordLoss(Request $request, int $order): RedirectResponse
    {
        $data = $request->validate([
            'operation_key' => ['required', 'string', 'max:64'],
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'loss_type' => ['required', Rule::in(['fire', 'missing'])],
            'note' => ['nullable', 'string', 'max:240'],
        ]);

        $this->perform(fn () => $this->operations->recordLoss(
            $this->companyId(),
            $order,
            (string) $data['operation_key'],
            (int) $data['product_id'],
            (string) $data['quantity'],
            (string) $data['loss_type'],
            $this->nullableString($data['note'] ?? null),
        ));

        return $this->back($order, 'Fire/eksik kaydı işlendi.');
    }

    public function receiveOutput(int $order): RedirectResponse
    {
        $this->perform(fn () => $this->operations->receiveOutput($this->companyId(), $order));

        return $this->back($order, 'Mamul stok girişi tamamlandı.');
    }

    public function complete(int $order): RedirectResponse
    {
        $this->perform(fn () => $this->operations->complete($this->companyId(), $order));

        return $this->back($order, 'Üretim emri tamamlandı ve kilitlendi.');
    }

    public function upload(Request $request, int $order): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:160'],
            'file' => ['required', 'file', 'max:51200'],
        ]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => 'Teknik dosya yüklenemedi.']);
        }

        $this->files->upload($order, $upload, $this->nullableString($data['label'] ?? null));

        return $this->back($order, 'Teknik dosya private arşive kaydedildi.');
    }

    public function download(int $order, int $attachment): StreamedResponse
    {
        return $this->files->download($order, $attachment);
    }

    public function detach(int $order, int $attachment): RedirectResponse
    {
        $this->files->detach($order, $attachment);

        return $this->back($order, 'Teknik dosya arşivlendi.');
    }

    private function order(int $id): ProductionOrder
    {
        return ProductionOrder::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function back(int $order, string $message): RedirectResponse
    {
        return redirect()->route('production.show', $order)->with('status', $message);
    }

    private function perform(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (DomainException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['production' => $exception->getMessage()]);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || trim((string) $value) === '' ? null : trim((string) $value);
    }

    private function companyId(): int
    {
        $key = $this->companyContext->requireCompany()->getKey();

        return is_int($key) ? $key : throw new LogicException('Production operation requires a persisted active company.');
    }
}
