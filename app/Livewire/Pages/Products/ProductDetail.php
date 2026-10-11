<?php

namespace App\Livewire\Pages\Products;

use App\Models\Period\DocumentLine;
use App\Models\Period\PriceListItem;
use App\Models\Period\Product;
use App\Models\Period\ProductCost;
use App\Models\Period\StockBalance;
use App\Models\Period\StockMovement;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class ProductDetail extends Component
{
    public Product $product;

    #[Url(as: 'sekme')]
    public string $activeTab = 'overview';

    public function mount(Product $product): void
    {
        $this->authorize('view', $product);
        $this->product = $product;
    }

    /** @return array<string, string> */
    private function tabs(): array
    {
        $tabs = [
            'overview' => 'Genel Bakış',
            'stock' => 'Stok Durumu',
            'movements' => 'Stok Hareketleri',
        ];

        if (auth()->user()?->can('sales_invoices.view')) {
            $tabs['sales'] = 'Satışlar';
            $tabs['buyers'] = 'En Çok Alan Cariler';
            $tabs['annual'] = 'Yıllık Performans';
        }

        if (auth()->user()?->can('supplier_invoices.view')) {
            $tabs['purchases'] = 'Alışlar';
            $tabs['suppliers'] = 'Tedarikçiler';
        }

        $tabs += [
            'prices' => 'Fiyatlar',
            'locations' => 'Depolar',
            'variants' => 'Varyantlar',
            'barcodes' => 'Barkodlar',
            'images' => 'Görseller',
            'notes' => 'Notlar',
            'files' => 'Dosyalar',
        ];

        return $tabs;
    }

    public function selectTab(string $tab): void
    {
        abort_unless(array_key_exists($tab, $this->tabs()), 403);
        $this->activeTab = $tab;
    }

    public function render(): View
    {
        $this->authorize('view', $this->product);
        $tabs = $this->tabs();

        if (! array_key_exists($this->activeTab, $tabs)) {
            $this->activeTab = 'overview';
        }

        $product = $this->product->loadMissing(['brand', 'category', 'unit']);
        $tab = $this->activeTab;
        $summary = StockBalance::query()
            ->where('product_id', $product->id)
            ->selectRaw('COALESCE(SUM(quantity), 0)::text AS physical')
            ->selectRaw('COALESCE(SUM(reserved + consignment_reserved), 0)::text AS reserved_total')
            ->selectRaw('COALESCE(SUM(quarantine), 0)::text AS quarantined')
            ->selectRaw('COALESCE(SUM(quantity - reserved - consignment_reserved - quarantine), 0)::text AS available_total')
            ->first();

        $balances = collect();
        $movements = collect();
        $sales = collect();
        $purchases = collect();
        $topBuyers = collect();
        $annual = collect();
        $prices = collect();
        $variants = collect();
        $attachments = collect();

        if (in_array($tab, ['stock', 'locations'], true)) {
            $balances = StockBalance::query()
                ->where('product_id', $product->id)
                ->with('location')
                ->orderBy('location_id')
                ->get();
        }

        if (in_array($tab, ['overview', 'movements'], true)) {
            $movements = StockMovement::query()
                ->where('product_id', $product->id)
                ->with('location')
                ->orderByDesc('movement_date')
                ->orderByDesc('id')
                ->limit($tab === 'overview' ? 8 : 50)
                ->get();
        }

        if (auth()->user()?->can('sales_invoices.view') && in_array($tab, ['overview', 'sales'], true)) {
            $sales = $this->postedLines('sales_invoice', $tab === 'overview' ? 5 : 50);
        }

        if (auth()->user()?->can('supplier_invoices.view') && in_array($tab, ['purchases', 'suppliers'], true)) {
            $purchases = $this->postedLines('supplier_invoice', 50);
        }

        if (auth()->user()?->can('sales_invoices.view') && $tab === 'buyers') {
            $topBuyers = DB::connection('period')->table('document_lines as line')
                ->join('documents as doc', 'doc.id', '=', 'line.document_id')
                ->join('contacts as contact', 'contact.id', '=', 'doc.contact_id')
                ->where('line.product_id', $product->id)
                ->where('doc.document_type', 'sales_invoice')
                ->where('doc.status', 'posted')
                ->groupBy('contact.id', 'contact.title')
                ->selectRaw('contact.title AS contact_name, SUM(line.quantity)::text AS quantity, COUNT(DISTINCT doc.id) AS documents')
                ->orderByRaw('SUM(line.quantity) DESC')
                ->limit(30)
                ->get();
        }

        if (auth()->user()?->can('sales_invoices.view') && $tab === 'annual') {
            $annual = DB::connection('period')->table('document_lines as line')
                ->join('documents as doc', 'doc.id', '=', 'line.document_id')
                ->where('line.product_id', $product->id)
                ->where('doc.document_type', 'sales_invoice')
                ->where('doc.status', 'posted')
                ->groupByRaw('EXTRACT(YEAR FROM doc.document_date)')
                ->selectRaw('EXTRACT(YEAR FROM doc.document_date)::int AS year, SUM(line.quantity)::text AS quantity, COUNT(DISTINCT doc.id) AS documents')
                ->orderByDesc('year')
                ->get();
        }

        if ($tab === 'prices') {
            $prices = PriceListItem::query()->where('product_id', $product->id)
                ->with('priceList')->orderByDesc('id')->get();
        }

        if ($tab === 'variants') {
            $variants = $product->variantValues()->with('attribute')->get();
        }

        if ($tab === 'images' || $tab === 'files' || $tab === 'overview') {
            $attachments = $product->attachments()->orderBy('sort_order')->orderBy('id')->get();
        }

        $canViewCost = auth()->user()?->can('cost.view') ?? false;
        $cost = $canViewCost && in_array($tab, ['overview', 'prices', 'purchases'], true)
            ? ProductCost::query()->where('product_id', $product->id)->first()
            : null;

        return view('livewire.pages.products.product-detail', compact(
            'product', 'tabs', 'summary', 'balances', 'movements', 'sales', 'purchases',
            'topBuyers', 'annual', 'prices', 'variants', 'attachments', 'cost', 'canViewCost'
        ))->layout('layouts.app', [
            'pageTitle' => 'Ürün Detay · '.$product->code.' · '.$product->name,
        ]);
    }

    /** @return Collection<int, DocumentLine> */
    private function postedLines(string $type, int $limit): Collection
    {
        return DocumentLine::query()
            ->where('product_id', $this->product->id)
            ->whereHas('document', fn (Builder $query) => $query
                ->where('document_type', $type)->where('status', 'posted'))
            ->with('document.contact')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
