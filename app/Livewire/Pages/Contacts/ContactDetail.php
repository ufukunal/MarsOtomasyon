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
        $balance = $contact->balance();

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
        if ($tab === 'movements') {
            $movements = ContactTransaction::query()
                ->where('contact_id', $contact->id)
                ->with('document')
                ->orderByDesc('transaction_date')->orderByDesc('id')
                ->paginate($this->perPage);
        } elseif ($tab === 'overview') {
            $movements = ContactTransaction::query()
                ->where('contact_id', $contact->id)->with('document')
                ->orderByDesc('transaction_date')->orderByDesc('id')
                ->limit(5)->get();
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
        if ($tab === 'products' && $sales) {
            $products = DB::connection('period')->table('document_lines as line')
                ->join('documents as doc', 'doc.id', '=', 'line.document_id')
                ->join('products as product', 'product.id', '=', 'line.product_id')
                ->where('doc.contact_id', $contact->id)
                ->where('doc.document_type', DocumentType::SalesInvoice->value)
                ->where('doc.status', 'posted')
                ->groupBy('product.id', 'product.code', 'product.name')
                ->selectRaw('product.id, product.code, product.name, SUM(line.quantity)::text AS quantity, COUNT(DISTINCT doc.id) AS document_count')
                ->orderByRaw('SUM(line.quantity) DESC')
                ->limit(50)->get();
        }

        $annual = collect();
        if ($tab === 'annual' && $sales) {
            $annual = Document::query()->where('contact_id', $contact->id)
                ->where('document_type', DocumentType::SalesInvoice->value)
                ->where('status', 'posted')
                ->groupByRaw('EXTRACT(YEAR FROM document_date), currency')
                ->selectRaw('EXTRACT(YEAR FROM document_date)::int AS year, currency, COUNT(*) AS document_count, SUM(grand_total)::text AS total')
                ->orderByDesc('year')->get();
        }

        $attachments = $tab === 'files' ? $contact->attachments()->get() : collect();

        return view('livewire.pages.contacts.contact-detail', compact(
            'contact', 'tabs', 'year', 'balance', 'yearSales', 'openOrders', 'openQuotes',
            'lastMovement', 'movements', 'documents', 'activity', 'products', 'annual',
            'attachments',
        ))->layout('layouts.app', ['pageTitle' => 'Cari Detay · '.$contact->title]);
    }
}
