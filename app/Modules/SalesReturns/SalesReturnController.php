<?php

namespace App\Modules\SalesReturns;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesReturns\Actions\AuthorizeSalesReturn;
use App\Modules\SalesReturns\Actions\CancelSalesReturn;
use App\Modules\SalesReturns\Actions\CompleteSalesReturn;
use App\Modules\SalesReturns\Actions\CreateSalesReturn;
use App\Modules\SalesReturns\Actions\ReceiveSalesReturn;
use App\Modules\SalesReturns\Actions\SalesReturnDraftData;
use App\Modules\SalesReturns\Actions\SalesReturnInspectionLineData;
use App\Modules\SalesReturns\Actions\SalesReturnLineData;
use App\Modules\SalesReturns\Models\SalesReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final readonly class SalesReturnController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CreateSalesReturn $createSalesReturn,
        private AuthorizeSalesReturn $authorizeSalesReturn,
        private ReceiveSalesReturn $receiveSalesReturn,
        private CompleteSalesReturn $completeSalesReturn,
        private CancelSalesReturn $cancelSalesReturn,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $query = SalesReturn::query()
            ->with(['account', 'salesInvoice'])
            ->where('company_id', $this->companyId());
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->whereRaw('number ILIKE ?', [$like])
                    ->orWhereHas('salesInvoice', fn ($invoice) => $invoice->whereRaw('number ILIKE ?', [$like]))
                    ->orWhereHas('account', fn ($account) => $account->whereRaw('legal_name ILIKE ?', [$like]));
            });
        }

        return view('sales-returns.index', [
            'returns' => $query->orderByDesc('return_date')->orderByDesc('id')->paginate(50)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        $companyId = $this->companyId();
        $invoices = SalesInvoice::query()
            ->with('account')
            ->where('company_id', $companyId)
            ->where('status', 'finalized')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();
        $selectedId = $request->old('sales_invoice_id', $request->query('sales_invoice_id'));
        $selectedInvoiceId = is_numeric($selectedId) ? (int) $selectedId : null;
        $selectedInvoice = $selectedInvoiceId === null
            ? null
            : $invoices->first(fn (SalesInvoice $invoice): bool => (int) $invoice->getKey() === $selectedInvoiceId);
        $remainingByLine = collect();
        if ($selectedInvoice instanceof SalesInvoice) {
            $selectedInvoice->load('lines');
            $reserved = DB::table('sales_return_lines as line')
                ->join('sales_returns as header', function ($join): void {
                    $join->on('header.company_id', '=', 'line.company_id')
                        ->on('header.id', '=', 'line.sales_return_id');
                })
                ->where('line.company_id', $companyId)
                ->where('line.sales_invoice_id', $selectedInvoice->getKey())
                ->whereIn('header.status', ['authorized', 'received', 'completed'])
                ->groupBy('line.sales_invoice_line_id')
                ->selectRaw('line.sales_invoice_line_id, SUM(line.quantity) AS quantity')
                ->pluck('quantity', 'line.sales_invoice_line_id');
            $remainingByLine = $selectedInvoice->lines->mapWithKeys(function ($line) use ($reserved): array {
                $used = (string) ($reserved->get($line->getKey()) ?? '0');
                $remaining = (string) DB::scalar('SELECT CAST(CAST(? AS numeric) - CAST(? AS numeric) AS text)', [(string) $line->quantity, $used]);

                return [(int) $line->getKey() => $remaining];
            });
        }

        return view('sales-returns.form', compact('invoices', 'selectedInvoice', 'remainingByLine'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'series_code' => ['nullable', 'string', 'max:64'],
            'sales_invoice_id' => ['required', 'integer'],
            'return_date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.sales_invoice_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'decimal:0,6', 'min:0'],
            'lines.*.reason_code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9]+(?:[._-][A-Za-z0-9]+)*$/'],
        ]);

        $lines = [];
        foreach ($validated['lines'] as $line) {
            $quantity = trim((string) $line['quantity']);
            if (preg_match('/^0+(?:\.0+)?$/D', $quantity) === 1) {
                continue;
            }
            $lines[] = new SalesReturnLineData(
                salesInvoiceLineId: (int) $line['sales_invoice_line_id'],
                quantity: $quantity,
                reasonCode: (string) $line['reason_code'],
            );
        }

        $return = $this->createSalesReturn->handle(new SalesReturnDraftData(
            salesInvoiceId: (int) $validated['sales_invoice_id'],
            returnDate: (string) $validated['return_date'],
            note: isset($validated['note']) ? (string) $validated['note'] : null,
            lines: $lines,
        ), (string) ($validated['series_code'] ?? 'default'));

        return redirect()->route('returns.show', $return->getKey())
            ->with('status', 'RMA taslağı oluşturuldu.');
    }

    public function show(int $salesReturn): View
    {
        $return = $this->salesReturn($salesReturn)->load(['account', 'salesInvoice', 'lines.salesInvoiceLine']);

        return view('sales-returns.show', ['return' => $return]);
    }

    public function authorize(int $salesReturn): RedirectResponse
    {
        $return = $this->authorizeSalesReturn->handle($salesReturn);

        return redirect()->route('returns.show', $return->getKey())
            ->with('status', 'RMA yetkilendirildi; iade kapasitesi rezerve edildi.');
    }

    public function receive(Request $request, int $salesReturn): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.sales_return_line_id' => ['required', 'integer'],
            'lines.*.accepted_quantity' => ['required', 'decimal:0,6', 'min:0'],
            'lines.*.rejected_quantity' => ['required', 'decimal:0,6', 'min:0'],
            'lines.*.restock_quantity' => ['required', 'decimal:0,6', 'min:0'],
            'lines.*.condition_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $inspection = array_map(
            static fn (array $line): SalesReturnInspectionLineData => new SalesReturnInspectionLineData(
                salesReturnLineId: (int) $line['sales_return_line_id'],
                acceptedQuantity: (string) $line['accepted_quantity'],
                rejectedQuantity: (string) $line['rejected_quantity'],
                restockQuantity: (string) $line['restock_quantity'],
                conditionNotes: isset($line['condition_notes']) ? (string) $line['condition_notes'] : null,
            ),
            array_values($validated['lines']),
        );
        $return = $this->receiveSalesReturn->handle($salesReturn, $inspection);

        return redirect()->route('returns.show', $return->getKey())
            ->with('status', 'RMA fiziksel kabul kontrolü kaydedildi; kredi ve stok dönüşleri sabitlendi.');
    }

    public function complete(int $salesReturn): RedirectResponse
    {
        $return = $this->completeSalesReturn->handle($salesReturn);

        return redirect()->route('returns.show', $return->getKey())
            ->with('status', 'RMA tamamlandı; müşteri cari kredisi ve kabul edilen stok dönüşü işlendi.');
    }

    public function cancel(int $salesReturn): RedirectResponse
    {
        $return = $this->cancelSalesReturn->handle($salesReturn);

        return redirect()->route('returns.show', $return->getKey())
            ->with('status', 'RMA iptal edildi.');
    }

    private function salesReturn(int $salesReturnId): SalesReturn
    {
        return SalesReturn::query()
            ->where('company_id', $this->companyId())
            ->whereKey($salesReturnId)
            ->firstOrFail();
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
