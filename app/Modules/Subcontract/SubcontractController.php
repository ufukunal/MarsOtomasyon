<?php

namespace App\Modules\Subcontract;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use App\Modules\Subcontract\Actions\SubcontractOperations;
use App\Modules\Subcontract\Files\SubcontractFileManager;
use App\Modules\Subcontract\Models\SubcontractOrder;
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

final readonly class SubcontractController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private SubcontractOperations $operations,
        private SubcontractFileManager $files,
    ) {}

    public function index(): View
    {
        $companyId = $this->companyId();

        return view('subcontract.index', [
            'orders' => SubcontractOrder::query()->where('company_id', $companyId)->with(['supplier', 'outputProduct'])->orderByDesc('id')->paginate(50),
            'suppliers' => Account::query()->where('company_id', $companyId)->where('status', 'active')->whereIn('type', ['supplier', 'mixed'])->orderBy('legal_name')->get(['id', 'code', 'legal_name']),
            'products' => Product::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'locations' => WarehouseLocation::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('warehouse_id')->orderBy('code')->get(['id', 'warehouse_id', 'code', 'name']),
        ]);
    }

    public function show(int $order): View
    {
        $model = $this->order($order);
        $model->load(['supplier', 'outputProduct', 'warehouse', 'location', 'materials.product', 'receipts', 'losses.product', 'events']);

        return view('subcontract.show', ['order' => $model, 'attachments' => $this->files->all($order)]);
    }

    public function report(): View
    {
        $rows = DB::table('subcontract_order_materials as material')
            ->join('subcontract_orders as orders', function ($join): void {
                $join->on('orders.company_id', '=', 'material.company_id')->on('orders.id', '=', 'material.subcontract_order_id');
            })
            ->join('products as product', function ($join): void {
                $join->on('product.company_id', '=', 'material.company_id')->on('product.id', '=', 'material.product_id');
            })
            ->join('accounts as supplier', function ($join): void {
                $join->on('supplier.company_id', '=', 'orders.company_id')->on('supplier.id', '=', 'orders.supplier_account_id');
            })
            ->where('material.company_id', $this->companyId())
            ->select([
                'orders.id', 'orders.order_no', 'orders.status', 'supplier.code as supplier_code', 'supplier.legal_name as supplier_name',
                'product.code as product_code', 'product.name as product_name', 'material.sent_quantity', 'material.sent_value',
                'material.consumed_quantity', 'material.consumed_value', 'material.loss_quantity', 'material.loss_value',
            ])
            ->selectRaw('(material.sent_quantity - material.consumed_quantity - material.loss_quantity) AS remaining_quantity')
            ->selectRaw('(material.sent_value - material.consumed_value - material.loss_value) AS remaining_value')
            ->orderByDesc('orders.id')->orderBy('product.code')->limit(1000)->get();

        return view('subcontract.report', ['rows' => $rows]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_account_id' => ['required', 'integer', 'min:1'], 'output_product_id' => ['required', 'integer', 'min:1'],
            'order_no' => ['required', 'string', 'max:64'], 'planned_output_quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'warehouse_id' => ['required', 'integer', 'min:1'], 'location_id' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'], 'materials' => ['required', 'array', 'min:1', 'max:10'],
            'materials.*.product_id' => ['nullable', 'integer', 'min:1'], 'materials.*.quantity' => ['nullable', 'decimal:0,6', 'gt:0'],
        ]);
        /** @var array<int, array<string, mixed>> $materialRows */
        $materialRows = $data['materials'];
        $materials = $this->materials($materialRows);
        $order = $this->perform(fn () => $this->operations->createOrder(
            $this->companyId(), (int) $data['supplier_account_id'], (int) $data['output_product_id'], (string) $data['order_no'],
            (string) $data['planned_output_quantity'], (int) $data['warehouse_id'], (int) $data['location_id'], $materials,
            $this->nullableString($data['note'] ?? null),
        ));

        return redirect()->route('subcontract.show', $order->getKey())->with('status', 'Fason sipariş oluşturuldu.');
    }

    public function send(int $order): RedirectResponse
    {
        $this->perform(fn () => $this->operations->sendMaterials($this->companyId(), $order));

        return $this->back($order, 'Fason malzeme gönderimi tamamlandı.');
    }

    public function loss(Request $request, int $order): RedirectResponse
    {
        $data = $request->validate([
            'operation_key' => ['required', 'string', 'max:64'], 'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'decimal:0,6', 'gt:0'], 'loss_type' => ['required', Rule::in(['fire', 'missing'])], 'note' => ['nullable', 'string', 'max:240'],
        ]);
        $this->perform(fn () => $this->operations->recordLoss($this->companyId(), $order, (string) $data['operation_key'], (int) $data['product_id'], (string) $data['quantity'], (string) $data['loss_type'], $this->nullableString($data['note'] ?? null)));

        return $this->back($order, 'Fason fire/eksik custody kaydı işlendi.');
    }

    public function receive(Request $request, int $order): RedirectResponse
    {
        $data = $request->validate([
            'operation_key' => ['required', 'string', 'max:64'], 'output_quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'consumption' => ['required', 'array', 'min:1', 'max:10'], 'consumption.*.product_id' => ['nullable', 'integer', 'min:1'], 'consumption.*.quantity' => ['nullable', 'decimal:0,6', 'gt:0'],
        ]);
        /** @var array<int, array<string, mixed>> $consumptionRows */
        $consumptionRows = $data['consumption'];
        $consumption = $this->materials($consumptionRows);
        $this->perform(fn () => $this->operations->receiveOutput($this->companyId(), $order, (string) $data['operation_key'], (string) $data['output_quantity'], $consumption));

        return $this->back($order, 'Fason mamul kabulü stok ve custody üzerinde işlendi.');
    }

    public function complete(int $order): RedirectResponse
    {
        $this->perform(fn () => $this->operations->complete($this->companyId(), $order));

        return $this->back($order, 'Fason sipariş reconcile edilerek tamamlandı.');
    }

    public function upload(Request $request, int $order): RedirectResponse
    {
        $data = $request->validate(['label' => ['nullable', 'string', 'max:160'], 'file' => ['required', 'file', 'max:51200']]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => 'Fason teknik dosyası yüklenemedi.']);
        }
        $this->files->upload($order, $upload, $this->nullableString($data['label'] ?? null));

        return $this->back($order, 'Fason teknik/fotoğraf/talimat dosyası arşive kaydedildi.');
    }

    public function download(int $order, int $attachment): StreamedResponse
    {
        return $this->files->download($order, $attachment);
    }

    public function detach(int $order, int $attachment): RedirectResponse
    {
        $this->files->detach($order, $attachment);

        return $this->back($order, 'Fason dosyası arşivlendi.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{product_id:int, quantity:string}>
     */
    private function materials(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $productId = $row['product_id'] ?? null;
            $quantity = $row['quantity'] ?? null;
            if ($productId === null && $quantity === null) {
                continue;
            }
            if ($productId === null || $quantity === null) {
                throw ValidationException::withMessages(['materials' => 'Ürün ve miktar birlikte girilmelidir.']);
            }
            $result[] = ['product_id' => (int) $productId, 'quantity' => (string) $quantity];
        }
        if ($result === []) {
            throw ValidationException::withMessages(['materials' => 'En az bir malzeme/tüketim satırı zorunludur.']);
        }

        return $result;
    }

    private function order(int $id): SubcontractOrder
    {
        return SubcontractOrder::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function back(int $order, string $message): RedirectResponse
    {
        return redirect()->route('subcontract.show', $order)->with('status', $message);
    }

    private function perform(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (DomainException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['subcontract' => $exception->getMessage()]);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || trim((string) $value) === '' ? null : trim((string) $value);
    }

    private function companyId(): int
    {
        $key = $this->companyContext->requireCompany()->getKey();

        return is_int($key) ? $key : throw new LogicException('Subcontract operation requires a persisted active company.');
    }
}
