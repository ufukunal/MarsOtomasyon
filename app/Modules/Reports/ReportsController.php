<?php

namespace App\Modules\Reports;

use App\Modules\Core\Company\ActiveCompanyContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ReportsController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private ReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $data = $this->reports->build($this->companyId(), $filters);

        return view('reports.index', $data + ['filters' => $filters]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $section = (string) $request->validate([
            'section' => ['required', 'in:finance,aging,stock,movements'],
        ])['section'];
        $data = $this->reports->build($this->companyId(), $filters);
        [$headers, $rows] = $this->exportRows($section, $data);

        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('CSV output stream could not be opened.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($output, $row, ';');
            }
            fclose($output);
        }, 'mars-report-'.$section.'-'.$filters['as_of'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array{as_of:string,currency:?string,warehouse_id:?int,account_type:?string} */
    private function filters(Request $request): array
    {
        $currency = strtoupper(trim((string) $request->query('currency', '')));
        $warehouse = $request->query('warehouse_id');
        $accountType = trim((string) $request->query('account_type', ''));

        $request->merge([
            'as_of' => $request->query('as_of', now()->toDateString()),
            'currency' => $currency === '' ? null : $currency,
            'warehouse_id' => $warehouse === '' ? null : $warehouse,
            'account_type' => $accountType === '' ? null : $accountType,
        ]);

        $validated = $request->validate([
            'as_of' => ['required', 'date_format:Y-m-d'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
            'account_type' => ['nullable', 'in:customer,supplier,mixed,clearing'],
        ]);

        return [
            'as_of' => (string) $validated['as_of'],
            'currency' => isset($validated['currency']) ? (string) $validated['currency'] : null,
            'warehouse_id' => isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
            'account_type' => isset($validated['account_type']) ? (string) $validated['account_type'] : null,
        ];
    }

    /**
     * @param  array{
     *     finance:list<array{currency:string,treasury:float,receivable:float,payable:float,net:float}>,
     *     aging:list<array{id:int,code:string,name:string,type:string,currency:string,current:float,days_1_30:float,days_31_60:float,days_61_90:float,days_90_plus:float,total:float}>,
     *     stock:list<array{product_id:int,product_code:string,product_name:string,warehouse_id:int,warehouse_code:string,warehouse_name:string,quantity:float,unit_cost:float,value:float}>,
     *     movements:\Illuminate\Support\Collection<int, object>,
     *     warehouses:\Illuminate\Support\Collection<int, object>
     * }  $data
     * @return array{0:list<string>,1:list<list<string|int|float>>}
     */
    private function exportRows(string $section, array $data): array
    {
        return match ($section) {
            'finance' => [
                ['Para Birimi', 'Treasury', 'Alacak', 'Borç', 'Net Pozisyon'],
                array_map(static fn (array $row): array => [
                    $row['currency'],
                    $row['treasury'],
                    $row['receivable'],
                    $row['payable'],
                    $row['net'],
                ], $data['finance']),
            ],
            'aging' => [
                ['Cari Kodu', 'Cari', 'Tip', 'Para Birimi', 'Vadesi Gelmemiş', '1-30', '31-60', '61-90', '90+', 'Toplam'],
                array_map(static fn (array $row): array => [
                    $row['code'],
                    $row['name'],
                    $row['type'],
                    $row['currency'],
                    $row['current'],
                    $row['days_1_30'],
                    $row['days_31_60'],
                    $row['days_61_90'],
                    $row['days_90_plus'],
                    $row['total'],
                ], $data['aging']),
            ],
            'stock' => [
                ['Ürün Kodu', 'Ürün', 'Depo Kodu', 'Depo', 'Miktar', 'Ort. Maliyet', 'Stok Değeri'],
                array_map(static fn (array $row): array => [
                    $row['product_code'],
                    $row['product_name'],
                    $row['warehouse_code'],
                    $row['warehouse_name'],
                    $row['quantity'],
                    $row['unit_cost'],
                    $row['value'],
                ], $data['stock']),
            ],
            'movements' => [
                ['Tarih', 'Ürün Kodu', 'Ürün', 'Depo', 'Hareket', 'Miktar', 'Birim Maliyet', 'Değer', 'Kaynak Tipi', 'Kaynak'],
                $data['movements']->map(static fn (object $row): array => [
                    (string) $row->occurred_at,
                    (string) $row->product_code,
                    (string) $row->product_name,
                    (string) $row->warehouse_code,
                    (string) $row->movement_type,
                    (string) $row->quantity_delta,
                    (string) $row->unit_cost,
                    (string) $row->value_delta,
                    (string) $row->source_type,
                    (string) $row->source_id,
                ])->all(),
            ],
            default => throw new RuntimeException('Unsupported report export section.'),
        };
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
