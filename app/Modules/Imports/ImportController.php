<?php

namespace App\Modules\Imports;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\GoodsReceipts\Models\GoodsReceiptLine;
use App\Modules\Imports\Actions\ImportOperations;
use App\Modules\Imports\Models\ImportFile;
use App\Modules\Products\Models\Product;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use LogicException;

final readonly class ImportController
{
    public function __construct(private ActiveCompanyContext $companyContext, private ImportOperations $operations) {}

    public function index(): View
    {
        $companyId = $this->companyId();

        return view('imports.index', [
            'files' => ImportFile::query()->where('company_id', $companyId)->with('supplier')->orderByDesc('id')->paginate(50),
            'suppliers' => Account::query()->where('company_id', $companyId)->where('status', 'active')->whereIn('type', ['supplier', 'mixed'])->orderBy('legal_name')->get(['id', 'code', 'legal_name']),
        ]);
    }

    public function show(int $file): View
    {
        $model = $this->file($file)->load(['supplier', 'containers', 'items.product', 'items.container', 'items.receiptLinks', 'expenses']);
        $productIds = $model->items->pluck('product_id')->all();
        $receiptLines = GoodsReceiptLine::query()
            ->join('goods_receipts as receipt', function ($join): void {
                $join->on('receipt.company_id', '=', 'goods_receipt_lines.company_id')->on('receipt.id', '=', 'goods_receipt_lines.goods_receipt_id');
            })
            ->where('goods_receipt_lines.company_id', $this->companyId())
            ->where('receipt.status', 'finalized')
            ->whereIn('goods_receipt_lines.product_id', $productIds)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('import_receipt_links as link')->whereColumn('link.company_id', 'goods_receipt_lines.company_id')->whereColumn('link.goods_receipt_line_id', 'goods_receipt_lines.id');
            })
            ->select(['goods_receipt_lines.id', 'goods_receipt_lines.goods_receipt_id', 'goods_receipt_lines.product_id', 'goods_receipt_lines.product_code', 'goods_receipt_lines.product_name', 'goods_receipt_lines.accepted_quantity', 'receipt.number as receipt_number'])
            ->orderByDesc('goods_receipt_lines.id')->limit(250)->get();
        $products = Product::query()->where('company_id', $this->companyId())->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']);
        $costs = DB::table('import_landed_cost_allocations as allocation')
            ->join('import_landed_cost_batches as batch', function ($join): void {
                $join->on('batch.company_id', '=', 'allocation.company_id')->on('batch.id', '=', 'allocation.landed_cost_batch_id');
            })->where('allocation.company_id', $this->companyId())->where('batch.import_file_id', $file)
            ->select(['batch.operation_key', 'batch.allocation_basis', 'batch.expense_total', 'allocation.allocated_amount', 'allocation.import_receipt_link_id'])->orderBy('allocation.id')->get();

        return view('imports.show', ['file' => $model, 'products' => $products, 'receiptLines' => $receiptLines, 'costs' => $costs, 'loading' => $this->operations->loadingSummary($file)]);
    }

    public function report(): View
    {
        $rows = DB::table('import_files as file')
            ->leftJoin('import_items as item', function ($join): void {
                $join->on('item.company_id', '=', 'file.company_id')->on('item.import_file_id', '=', 'file.id');
            })
            ->leftJoin('import_receipt_links as link', function ($join): void {
                $join->on('link.company_id', '=', 'item.company_id')->on('link.import_item_id', '=', 'item.id');
            })
            ->where('file.company_id', $this->companyId())
            ->groupBy('file.id', 'file.number', 'file.status', 'file.currency_code', 'file.expected_arrival_date')
            ->select(['file.id', 'file.number', 'file.status', 'file.currency_code', 'file.expected_arrival_date'])
            ->selectRaw('COUNT(DISTINCT item.id)::int AS item_count')
            ->selectRaw('CAST(COALESCE(SUM(DISTINCT item.quantity),0) AS numeric(20,6))::text AS item_quantity')
            ->selectRaw('CAST(COALESCE(SUM(link.linked_quantity),0) AS numeric(20,6))::text AS received_quantity')
            ->orderByDesc('file.id')->limit(1000)->get();

        return view('imports.report', ['rows' => $rows]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:64'], 'currency_code' => ['required', 'string', 'size:3'], 'supplier_account_id' => ['nullable', 'integer', 'min:1'],
            'supplier_reference' => ['nullable', 'string', 'max:100'], 'origin_country' => ['nullable', 'string', 'max:100'],
            'loading_port' => ['nullable', 'string', 'max:150'], 'destination_port' => ['nullable', 'string', 'max:150'],
            'departure_date' => ['nullable', 'date'], 'expected_arrival_date' => ['nullable', 'date', 'after_or_equal:departure_date'], 'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $model = $this->perform(fn () => $this->operations->createFile((string) $data['number'], (string) $data['currency_code'], isset($data['supplier_account_id']) ? (int) $data['supplier_account_id'] : null, $this->nullable($data['supplier_reference'] ?? null), $this->nullable($data['origin_country'] ?? null), $this->nullable($data['loading_port'] ?? null), $this->nullable($data['destination_port'] ?? null), $this->nullable($data['departure_date'] ?? null), $this->nullable($data['expected_arrival_date'] ?? null), $this->nullable($data['note'] ?? null)));

        return redirect()->route('import.show', $model->getKey())->with('status', 'İthalat dosyası oluşturuldu.');
    }

    public function container(Request $request, int $file): RedirectResponse
    {
        $data = $request->validate(['container_no' => ['required', 'string', 'max:32'], 'seal_no' => ['nullable', 'string', 'max:64'], 'container_type' => ['nullable', 'string', 'max:32'], 'max_weight_kg' => ['nullable', 'decimal:0,6', 'gt:0'], 'max_volume_m3' => ['nullable', 'decimal:0,6', 'gt:0'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->perform(fn () => $this->operations->addContainer($file, (string) $data['container_no'], $this->nullable($data['seal_no'] ?? null), $this->nullable($data['container_type'] ?? null), $this->nullable($data['max_weight_kg'] ?? null), $this->nullable($data['max_volume_m3'] ?? null), $this->nullable($data['note'] ?? null)));

        return $this->back($file, 'Konteyner eklendi.');
    }

    public function item(Request $request, int $file): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'min:1'], 'quantity' => ['required', 'decimal:0,6', 'gt:0'], 'import_container_id' => ['nullable', 'integer', 'min:1'], 'package_reference' => ['nullable', 'string', 'max:100'], 'component_reference' => ['nullable', 'string', 'max:100'], 'package_count' => ['nullable', 'integer', 'min:0'], 'gross_weight_kg' => ['nullable', 'decimal:0,6', 'min:0'], 'net_weight_kg' => ['nullable', 'decimal:0,6', 'min:0'], 'volume_m3' => ['nullable', 'decimal:0,6', 'min:0'], 'material_location' => ['nullable', 'string', 'max:2000'], 'subcontract_collection' => ['nullable', 'boolean'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->perform(fn () => $this->operations->addItem($file, (int) $data['product_id'], (string) $data['quantity'], isset($data['import_container_id']) ? (int) $data['import_container_id'] : null, $this->nullable($data['package_reference'] ?? null), $this->nullable($data['component_reference'] ?? null), (int) ($data['package_count'] ?? 0), (string) ($data['gross_weight_kg'] ?? '0'), (string) ($data['net_weight_kg'] ?? '0'), (string) ($data['volume_m3'] ?? '0'), $this->nullable($data['material_location'] ?? null), (bool) ($data['subcontract_collection'] ?? false), $this->nullable($data['note'] ?? null)));

        return $this->back($file, 'İthalat kalemi eklendi.');
    }

    public function expense(Request $request, int $file): RedirectResponse
    {
        $data = $request->validate(['expense_code' => ['required', 'string', 'max:64'], 'description' => ['required', 'string', 'max:200'], 'amount' => ['required', 'decimal:0,6', 'gt:0'], 'currency_code' => ['required', 'string', 'size:3'], 'allocation_basis' => ['required', Rule::in(['line_value', 'quantity'])], 'final' => ['nullable', 'boolean'], 'note' => ['nullable', 'string', 'max:2000']]);
        $this->perform(fn () => $this->operations->recordExpense($file, (string) $data['expense_code'], (string) $data['description'], (string) $data['amount'], (string) $data['currency_code'], (string) $data['allocation_basis'], (bool) ($data['final'] ?? false), $this->nullable($data['note'] ?? null)));

        return $this->back($file, 'İthalat masrafı kaydedildi.');
    }

    public function finalizeExpense(int $file, int $expense): RedirectResponse
    {
        $this->perform(fn () => $this->operations->finalizeExpense($file, $expense));

        return $this->back($file, 'Masraf kesinleştirildi.');
    }

    public function inTransit(int $file): RedirectResponse
    {
        $this->perform(fn () => $this->operations->markInTransit($file));

        return $this->back($file, 'İthalat dosyası yolda durumuna alındı.');
    }

    public function arrived(Request $request, int $file): RedirectResponse
    {
        $data = $request->validate(['arrival_date' => ['nullable', 'date']]);
        $this->perform(fn () => $this->operations->markArrived($file, $this->nullable($data['arrival_date'] ?? null)));

        return $this->back($file, 'İthalat dosyası varışa alındı.');
    }

    public function receiptLink(Request $request, int $file): RedirectResponse
    {
        $data = $request->validate(['import_item_id' => ['required', 'integer', 'min:1'], 'goods_receipt_line_id' => ['required', 'integer', 'min:1']]);
        $this->perform(fn () => $this->operations->linkReceiptLine($file, (int) $data['import_item_id'], (int) $data['goods_receipt_line_id']));

        return $this->back($file, 'Mal kabul satırı ithalat kalemine bağlandı.');
    }

    public function landedCost(Request $request, int $file): RedirectResponse
    {
        $data = $request->validate(['operation_key' => ['required', 'string', 'max:64'], 'allocation_basis' => ['required', Rule::in(['line_value', 'quantity'])]]);
        $this->perform(fn () => $this->operations->postLandedCost($file, (string) $data['operation_key'], (string) $data['allocation_basis']));

        return $this->back($file, 'Landed-cost stok maliyet otoritesine post edildi.');
    }

    public function complete(int $file): RedirectResponse
    {
        $this->perform(fn () => $this->operations->complete($file));

        return $this->back($file, 'İthalat dosyası reconcile edilerek tamamlandı.');
    }

    public function pickingList(int $file): View
    {
        $model = $this->file($file)->load(['items.product', 'items.container']);

        return view('imports.picking-list', ['file' => $model]);
    }

    public function subcontractList(int $file): View
    {
        $model = $this->file($file);
        $rows = $this->operations->subcontractCollectionRows($file)->load(['product', 'container']);

        return view('imports.subcontract-list', ['file' => $model, 'rows' => $rows]);
    }

    public function loadingSimulator(int $file): View
    {
        $model = $this->file($file)->load('containers');
        $rows = DB::table('import_containers as container')->leftJoin('import_items as item', function ($join): void {
            $join->on('item.company_id', '=', 'container.company_id')->on('item.import_container_id', '=', 'container.id');
        })->where('container.company_id', $this->companyId())->where('container.import_file_id', $file)
            ->groupBy('container.id', 'container.container_no', 'container.max_weight_kg', 'container.max_volume_m3')
            ->select(['container.id', 'container.container_no', 'container.max_weight_kg', 'container.max_volume_m3'])
            ->selectRaw('CAST(COALESCE(SUM(item.gross_weight_kg),0) AS numeric(20,6))::text AS gross_weight_kg')
            ->selectRaw('CAST(COALESCE(SUM(item.volume_m3),0) AS numeric(20,6))::text AS volume_m3')
            ->orderBy('container.container_no')->get();

        return view('imports.loading-simulator', ['file' => $model, 'rows' => $rows]);
    }

    private function file(int $id): ImportFile
    {
        return ImportFile::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function companyId(): int
    {
        $key = $this->companyContext->requireCompany()->getKey();

        return is_int($key) ? $key : throw new LogicException('Import operation requires a persisted active company.');
    }

    private function back(int $file, string $message): RedirectResponse
    {
        return redirect()->route('import.show', $file)->with('status', $message);
    }

    private function perform(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['import' => $exception->getMessage()]);
        }
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
