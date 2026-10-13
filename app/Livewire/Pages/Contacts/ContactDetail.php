<?php

namespace App\Livewire\Pages\Contacts;

use App\Enums\DocumentType;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ContactDetail extends Component
{
    use WithPagination;

    public Contact $contact;

    #[Url(as: 'sekme')]
    public string $activeTab = 'overview';

    public int $perPage = 25;

    #[Url(as: 'gorunum')]
    public string $statementMode = 'summary';

    #[Url(as: 'baslangic')]
    public ?string $statementFrom = null;

    #[Url(as: 'bitis')]
    public ?string $statementTo = null;

    public function updatedStatementFrom(): void
    {
        $this->resetPage();
    }

    public function updatedStatementTo(): void
    {
        $this->resetPage();
    }

    /** @return array{0:?string,1:?string} */
    private function statementRange(): array
    {
        $from = $this->statementFrom;
        $to = $this->statementTo;
        foreach ([$from, $to] as $date) {
            if ($date === null || $date === '') {
                continue;
            }
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $valid = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date) === 1
                && $parsed instanceof \DateTimeImmutable
                && $parsed->format('Y-m-d') === $date;
            abort_unless($valid, 422);
        }
        abort_if($from && $to && $to < $from, 422);
        return [$from ?: null, $to ?: null];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<ContactTransaction> */
    private function filteredMovementQuery(): \Illuminate\Database\Eloquent\Builder
    {
        [$from, $to] = $this->statementRange();
        return ContactTransaction::query()
            ->where('contact_id', $this->contact->id)
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to));
    }

    /** @return array<string,string> */
    private function openingBalances(): array
    {
        [$from] = $this->statementRange();
        if (! $from) {
            return [];
        }
        return DB::connection('period')->table('contact_transactions')
            ->where('contact_id', $this->contact->id)
            ->whereDate('transaction_date', '<', $from)
            ->groupBy('currency')
            ->selectRaw("currency, COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 0)::text AS opening")
            ->get()->pluck('opening', 'currency')->all();
    }

    /** @return list<list<string>> */
    private function statementRows(): array
    {
        $this->authorize('view', $this->contact);
        $opening = $this->openingBalances();
        $balances = $opening;
        $rows = [];
        foreach ($this->filteredMovementQuery()->with('document')
            ->orderBy('transaction_date')->orderBy('id')->get() as $movement) {
            $currency = (string) $movement->currency;
            $amount = (string) $movement->amount;
            $delta = $movement->direction === 'debit' ? $amount : bcmul($amount, '-1', 4);
            $balances[$currency] = bcadd($balances[$currency] ?? '0', $delta, 4);
            $rows[] = [
                $movement->transaction_date?->format('d.m.Y') ?? '',
                (string) ($movement->document?->number ?? ''),
                (string) $movement->transaction_type,
                (string) ($movement->description ?? ''),
                $movement->direction === 'debit' ? $amount : '',
                $movement->direction === 'credit' ? $amount : '',
                $balances[$currency],
                $currency,
            ];
        }
        return $rows;
    }

    public function exportStatementCsv(): StreamedResponse
    {
        $rows = $this->statementRows();
        $filename = 'cari-ekstre-'.$this->contact->code.'.csv';
        return response()->streamDownload(static function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Tarih','Belge','İşlem','Açıklama','Borç','Alacak','Bakiye','PB'], ';');
            foreach ($rows as $row) {
                // Spreadsheet formula injection cannot originate from free-text descriptions.
                $safe = array_map(static fn (string $v): string => preg_match('/^[=+@\-\t\r]/u', $v) ? "'".$v : $v, $row);
                fputcsv($output, $safe, ';');
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportStatementXlsx(): StreamedResponse
    {
        $rows = $this->statementRows();
        $filename = 'cari-ekstre-'.$this->contact->code.'.xlsx';
        return response()->streamDownload(static function () use ($rows): void {
            $book = new Spreadsheet();
            $sheet = $book->getActiveSheet();
            foreach ([['Tarih','Belge','İşlem','Açıklama','Borç','Alacak','Bakiye','PB'], ...$rows] as $rowIndex => $row) {
                foreach ($row as $colIndex => $value) {
                    $sheet->setCellValueExplicit(
                        \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1).($rowIndex + 1),
                        (string) $value,
                        DataType::TYPE_STRING,
                    );
                }
            }
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }


    /** @return list<string> Only permitted document types are ever returned. */
    private function documentKindsForTab(string $tab): array
    {
        return array_values(match ($tab) {
            'invoices' => array_filter([
                auth()->user()?->can('sales_invoices.view') ? DocumentType::SalesInvoice->value : null,
                auth()->user()?->can('supplier_invoices.view') ? DocumentType::SupplierInvoice->value : null,
            ]),
            'orders' => array_filter([
                auth()->user()?->can('sales_orders.view') ? DocumentType::SalesOrder->value : null,
                auth()->user()?->can('purchase_orders.view') ? DocumentType::PurchaseOrder->value : null,
            ]),
            'quotes' => auth()->user()?->can('quotes.view') ? [DocumentType::Quote->value] : [],
            default => [],
        });
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Document> */
    private function documentQueryForTab(string $tab): \Illuminate\Database\Eloquent\Builder
    {
        $this->authorize('view', $this->contact);
        abort_unless(in_array($tab, ['invoices', 'orders', 'quotes'], true), 422);
        $types = $this->documentKindsForTab($tab);
        abort_if($types === [], 403);

        return Document::query()
            ->where('contact_id', $this->contact->id)
            ->whereIn('document_type', $types)
            ->orderByDesc('document_date')
            ->orderByDesc('id');
    }

    /** @return \Generator<list<string>> */
    private function documentExportRows(string $tab): \Generator
    {
        foreach ($this->documentQueryForTab($tab)->cursor() as $document) {
            yield [
                (string) ($document->number ?? 'Taslak'),
                $document->document_date?->format('d.m.Y') ?? '',
                $document->document_type->value,
                $document->valid_until?->format('d.m.Y') ?? '',
                $document->due_date?->format('d.m.Y') ?? '',
                (string) $document->tax_base,
                (string) $document->vat_amount,
                (string) $document->grand_total,
                (string) $document->currency,
                (string) $document->status,
            ];
        }
    }

    /** @return list<string> */
    private static function documentExportHeadings(): array
    {
        return ['Belge No', 'Tarih', 'Tür', 'Geçerlilik', 'Vade', 'Matrah', 'KDV', 'Toplam', 'Para Birimi', 'Durum'];
    }

    public function exportContactDocumentsCsv(): StreamedResponse
    {
        $tab = $this->activeTab;
        // Check permissions before response headers are sent.
        $this->documentQueryForTab($tab);
        $name = 'cari-'.$this->contact->code.'-'.$tab.'.csv';

        return response()->streamDownload(function () use ($tab): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::documentExportHeadings(), ';');
            foreach ($this->documentExportRows($tab) as $row) {
                $row = array_map(
                    static fn (string $field): string => preg_match('/^[=+\-@\t\r]/u', $field) ? "'".$field : $field,
                    $row
                );
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportContactDocumentsXlsx(): StreamedResponse
    {
        $tab = $this->activeTab;
        $this->documentQueryForTab($tab);
        $name = 'cari-'.$this->contact->code.'-'.$tab.'.xlsx';

        return response()->streamDownload(function () use ($tab): void {
            $book = new Spreadsheet();
            $sheet = $book->getActiveSheet();
            $index = 1;
            foreach ((function () use ($tab) {
                yield self::documentExportHeadings();
                yield from $this->documentExportRows($tab);
            })() as $row) {
                foreach ($row as $column => $value) {
                    $sheet->setCellValueExplicit(
                        \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column + 1).$index,
                        (string) $value,
                        DataType::TYPE_STRING
                    );
                }
                $index++;
            }
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function mount(Contact $contact): void
    {
        $this->authorize('view', $contact);
        $this->contact = $contact;
    }

    /** @return array<string,string> */
    private function tabs(): array
    {
        $tabs = ['overview' => 'Genel Bakış'];

        if (auth()->user()?->can('sales_invoices.view') || auth()->user()?->can('supplier_invoices.view')) {
            $tabs['invoices'] = 'Faturalar';
        }
        if (auth()->user()?->can('sales_orders.view') || auth()->user()?->can('purchase_orders.view')) {
            $tabs['orders'] = 'Siparişler';
        }
        if (auth()->user()?->can('quotes.view')) {
            $tabs['quotes'] = 'Teklifler';
        }

        $tabs['movements'] = 'Cari Hareketler';

        if (auth()->user()?->can('sales_invoices.view')) {
            $tabs['products'] = 'Ürün Analizi';
            $tabs['annual'] = 'Yıllık Performans';
        }

        return $tabs + [
            'addresses' => 'Adresler',
            'people' => 'İletişim',
            'invoice_info' => 'Fatura Bilgileri',
            'shipping' => 'Sevk Bilgileri',
            'banks' => 'Banka Bilgileri',
            'notes' => 'Özel Açıklamalar',
            'b2b' => 'B2B',
            'files' => 'Dosyalar',
        ];
    }

    public function selectTab(string $tab): void
    {
        abort_unless(array_key_exists($tab, $this->tabs()), 403);
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function switchStatementMode(string $mode): void
    {
        abort_unless(in_array($mode, ['vouchers', 'summary', 'detailed'], true), 422);
        $this->authorize('view', $this->contact);
        $this->activeTab = 'movements';
        $this->statementMode = $mode;
        $this->resetPage();
    }

    public function setPerPage(int $value): void
    {
        abort_unless(in_array($value, [25, 50, 100], true), 422);
        $this->perPage = $value;
        $this->resetPage();
    }

    /** @return array<string,string> */
    public static function statusLabels(): array
    {
        return [
            'draft' => 'Taslak',
            'internal_review' => 'İç İnceleme',
            'customer_review' => 'Müşteri İncelemesi',
            'approved' => 'Onaylı',
            'confirmed' => 'Onaylandı',
            'posted' => 'Kesinleşti',
            'cancelled' => 'İptal',
            'reversed' => 'Ters Kayıt',
        ];
    }

    public function documentUrl(Document $document): ?string
    {
        $type = $document->document_type->value;

        $links = [
            DocumentType::Quote->value => ['quotes.view', 'sales.quotes.edit'],
            DocumentType::SalesOrder->value => ['sales_orders.view', 'sales.orders.edit'],
            DocumentType::Dispatch->value => ['dispatches.view', 'sales.dispatches.edit'],
            DocumentType::SalesInvoice->value => ['sales_invoices.view', 'sales.invoices.edit'],
            DocumentType::PurchaseOrder->value => ['purchase_orders.view', 'purchases.orders.edit'],
            DocumentType::GoodsReceipt->value => ['goods_receipts.view', 'purchases.receipts.edit'],
            DocumentType::SupplierInvoice->value => ['supplier_invoices.view', 'purchases.invoices.edit'],
            DocumentType::Proforma->value => ['proformas.view', 'sales.proformas.show'],
        ];

        if (! isset($links[$type]) || ! auth()->user()?->can($links[$type][0])) {
            return null;
        }

        return route($links[$type][1], ['id' => $document->id]);
    }

    public function render(): View
    {
        $this->authorize('view', $this->contact);

        $tabs = $this->tabs();
        if (! array_key_exists($this->activeTab, $tabs)) {
            $this->activeTab = 'overview';
        }

        $contact = $this->contact->loadMissing(['people', 'addresses', 'banks', 'categories', 'priceList']);
        $tab = $this->activeTab;
        $year = (int) now()->year;
        // NEVER combine separate currencies into a fictitious TRY balance.
        $currencyBalances = DB::connection('period')->table('contact_transactions')
            ->where('contact_id', $contact->id)
            ->groupBy('currency')
            ->selectRaw("currency, COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 0)::text AS balance")
            ->orderBy('currency')->get();
        $balanceCurrency = $currencyBalances->count() === 1 ? (string) $currencyBalances->first()->currency : 'TRY';
        $multiCurrency = $currencyBalances->count() > 1;
        $balance = $multiCurrency ? '0.0000' : (string) ($currencyBalances->first()?->balance ?? '0.0000');

        $sales = auth()->user()?->can('sales_invoices.view') ?? false;
        $ordersAllowed = auth()->user()?->can('sales_orders.view') ?? false;
        $quotesAllowed = auth()->user()?->can('quotes.view') ?? false;

        // Read values from actual period records. Other currencies are not silently summed with TRY.
        $yearSales = $sales
            ? Document::query()->where('contact_id', $contact->id)
                ->where('document_type', DocumentType::SalesInvoice->value)
                ->where('status', 'posted')
                ->where('currency', 'TRY')
                ->whereYear('document_date', $year)
                ->sum('grand_total')
            : null;

        $openOrders = $ordersAllowed
            ? Document::query()->where('contact_id', $contact->id)
                ->where('document_type', DocumentType::SalesOrder->value)
                ->whereIn('status', ['draft', 'approved', 'confirmed'])
                ->count()
            : null;

        $openQuotes = $quotesAllowed
            ? Document::query()->where('contact_id', $contact->id)
                ->where('document_type', DocumentType::Quote->value)
                ->whereIn('status', ['draft', 'internal_review', 'customer_review', 'approved'])
                ->count()
            : null;

        $lastMovement = ContactTransaction::query()
            ->where('contact_id', $contact->id)->orderByDesc('transaction_date')
            ->orderByDesc('id')->first();

        $movements = null;
        $movementTotals = collect();
        if (in_array($tab, ['overview', 'movements'], true)) {
            // Window function computes each transaction's running balance in chronological
            // order, before display sorting and pagination, partitioned by currency.
            $moveQuery = ($tab === 'movements'
                ? $this->filteredMovementQuery()
                : ContactTransaction::query()->where('contact_id', $contact->id))
                ->select('contact_transactions.*')
                ->selectRaw("(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END)
                    OVER (PARTITION BY currency ORDER BY transaction_date, id
                    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW))::text AS running_balance")
                ->with('document');
            if ($tab === 'movements') {
                if ($this->statementMode === 'detailed') {
                    $moveQuery->with(['document.lines.product', 'document.lines.unit']);
                }
                $movements = $moveQuery->orderBy('transaction_date')->orderBy('id')
                    ->paginate($this->perPage);
                $opening = $this->openingBalances();
                foreach ($movements->getCollection() as $movement) {
                    $movement->setAttribute('running_balance',
                        bcadd((string) ($opening[$movement->currency] ?? '0'), (string) $movement->running_balance, 4));
                }
                [$statementFrom, $statementTo] = $this->statementRange();
                $movementTotals = DB::connection('period')->table('contact_transactions')
                    ->where('contact_id', $contact->id)
                    ->when($statementFrom, fn ($q) => $q->whereDate('transaction_date', '>=', $statementFrom))
                    ->when($statementTo, fn ($q) => $q->whereDate('transaction_date', '<=', $statementTo))
                    ->groupBy('currency')
                    ->selectRaw("currency, SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END)::text AS total_debit")
                    ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END)::text AS total_credit")
                    ->selectRaw('COUNT(*)::int AS total_movements')
                    ->orderBy('currency')->get();
            } else {
                $movements = $moveQuery->orderByDesc('transaction_date')
                    ->orderByDesc('id')->limit(5)->get();
            }
        }

        $documents = null;
        if (in_array($tab, ['invoices', 'orders', 'quotes'], true)
            && $this->documentKindsForTab($tab) !== []) {
            $documents = $this->documentQueryForTab($tab)->paginate($this->perPage);
        }

        $activity = collect();
        if ($tab === 'overview') {
            $activity = Document::query()->where('contact_id', $contact->id)
                ->whereIn('document_type', array_filter([
                    $ordersAllowed ? DocumentType::SalesOrder->value : null,
                    $quotesAllowed ? DocumentType::Quote->value : null,
                    $sales ? DocumentType::SalesInvoice->value : null,
                ]))
                ->whereIn('status', ['draft', 'approved', 'confirmed'])
                ->orderByDesc('document_date')->orderByDesc('id')->limit(5)->get();
        }

        $products = collect();
        $productCategories = collect();
        $recentProductLines = collect();
        $productAnalysis = null;
        if ($tab === 'products' && $sales) {
            $salesLines = DB::connection('period')->table('document_lines as line')
                ->join('documents as doc', 'doc.id', '=', 'line.document_id')
                ->join('products as product', 'product.id', '=', 'line.product_id')
                ->where('doc.contact_id', $contact->id)
                ->where('doc.document_type', DocumentType::SalesInvoice->value)
                ->where('doc.status', 'posted')
                ->where('doc.currency', 'TRY');

            $products = (clone $salesLines)
                ->leftJoin('units as unit', 'unit.id', '=', 'line.unit_id')
                ->groupBy('product.id', 'product.code', 'product.name', 'unit.name')
                ->selectRaw('product.id, product.code, product.name, unit.name AS unit_name')
                ->selectRaw('SUM(line.quantity)::text AS quantity, COUNT(DISTINCT doc.id) AS document_count')
                ->selectRaw('SUM(line.line_total)::text AS revenue, MAX(doc.document_date) AS last_sale_at')
                ->orderByRaw('SUM(line.line_total) DESC')
                ->limit(50)->get();

            $categoryRows = (clone $salesLines)
                ->leftJoin('product_categories as category', 'category.id', '=', 'product.category_id')
                ->groupBy('category.name')
                ->selectRaw("COALESCE(category.name, 'Kategorisiz') AS category")
                ->selectRaw('SUM(line.line_total)::text AS revenue')
                ->orderByRaw('SUM(line.line_total) DESC')->limit(12)->get();

            // Category rows are capped for display; compute the denominator from ALL
            // posted TRY lines so the percentages and totals never become truncated.
            $categoryTotal = (string) (clone $salesLines)->sum('line.line_total');
            $productCategories = $categoryRows->map(function ($row) use ($categoryTotal) {
                $row->percent = bccomp($categoryTotal, '0', 4) > 0
                    ? min(100, max(0, (float) bcmul(bcdiv((string) $row->revenue, $categoryTotal, 6), '100', 2)))
                    : 0;
                return $row;
            });

            $recentProductLines = (clone $salesLines)
                ->leftJoin('units as unit', 'unit.id', '=', 'line.unit_id')
                ->selectRaw('doc.number, doc.document_date, product.id AS product_id, product.code, product.name')
                ->selectRaw('unit.name AS unit_name, line.quantity, line.unit_price, line.line_total')
                ->orderByDesc('doc.document_date')->orderByDesc('line.id')->limit(8)->get();

            $productAnalysis = [
                'distinct' => (clone $salesLines)->distinct()->count('product.id'),
                'top_product' => $products->first()?->code,
                'revenue_try' => $categoryTotal,
            ];
        }

        $annual = collect();
        $monthly = collect();
        $annualSummary = null;
        if ($tab === 'annual' && $sales) {
            $annual = Document::query()->where('contact_id', $contact->id)
                ->where('document_type', DocumentType::SalesInvoice->value)
                ->where('status', 'posted')
                ->groupByRaw('EXTRACT(YEAR FROM document_date), currency')
                ->selectRaw('EXTRACT(YEAR FROM document_date)::int AS year, currency, COUNT(*) AS document_count, SUM(grand_total)::text AS total')
                ->orderByDesc('year')->get();

            $monthly = Document::query()->where('contact_id', $contact->id)
                ->where('document_type', DocumentType::SalesInvoice->value)
                ->where('status', 'posted')->where('currency', 'TRY')
                ->whereYear('document_date', $year)
                ->groupByRaw('EXTRACT(MONTH FROM document_date)')
                ->selectRaw('EXTRACT(MONTH FROM document_date)::int AS month, COUNT(*) AS invoices')
                ->selectRaw('SUM(grand_total)::text AS revenue')
                ->orderBy('month')->get();

            $annualSummary = [
                'sales_try' => (string) ($yearSales ?? '0'),
                'sales_count' => $monthly->sum('invoices'),
                'months' => $monthly->count(),
                'last_month' => $monthly->last()?->month,
            ];
        }

        $attachments = $tab === 'files' ? $contact->attachments()->get() : collect();
        $addresses = $tab === 'shipping'
            ? $contact->addresses->where('type', 'shipping')
            : $contact->addresses;

        return view('livewire.pages.contacts.contact-detail', compact(
            'contact', 'tabs', 'year', 'balance', 'yearSales', 'openOrders', 'openQuotes',
            'lastMovement', 'movements', 'movementTotals', 'documents', 'activity',
            'products', 'productCategories', 'recentProductLines', 'productAnalysis',
            'annual', 'monthly', 'annualSummary', 'currencyBalances', 'balanceCurrency',
            'multiCurrency', 'attachments', 'addresses',
        ))->layout('layouts.app', ['pageTitle' => 'Cari Detay · '.$contact->title]);
    }
}
