<?php

namespace App\Livewire\Pages\Contacts;

use App\Enums\DocumentType;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
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
            $moveQuery = ContactTransaction::query()
                ->where('contact_id', $contact->id)
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
                $movementTotals = DB::connection('period')->table('contact_transactions')
                    ->where('contact_id', $contact->id)->groupBy('currency')
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
        $documentKinds = match ($tab) {
            'invoices' => array_filter([
                $sales ? DocumentType::SalesInvoice->value : null,
                auth()->user()?->can('supplier_invoices.view') ? DocumentType::SupplierInvoice->value : null,
            ]),
            'orders' => array_filter([
                $ordersAllowed ? DocumentType::SalesOrder->value : null,
                auth()->user()?->can('purchase_orders.view') ? DocumentType::PurchaseOrder->value : null,
            ]),
            'quotes' => $quotesAllowed ? [DocumentType::Quote->value] : [],
            default => [],
        };
        if ($documentKinds !== []) {
            $documents = Document::query()->where('contact_id', $contact->id)
                ->whereIn('document_type', $documentKinds)
                ->orderByDesc('document_date')->orderByDesc('id')
                ->paginate($this->perPage);
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

            $categoryTotal = '0.0000';
            foreach ($categoryRows as $row) {
                $categoryTotal = bcadd($categoryTotal, (string) $row->revenue, 4);
            }
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
                'distinct' => $products->count(),
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
