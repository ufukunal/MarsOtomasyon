@extends('layouts.app')

@section('title', 'Ürün/Stok')

@section('app-content')
    @php($productFamilyVariantEnabled = app(\App\Foundation\Features\FeatureRegistry::class)->enabled(\App\Foundation\Features\FeatureKey::ProductFamilyVariant))

    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürünler ve Hizmetler</p>
            <h1>Ürün Listesi</h1>
            <p>Aktif firmaya ait SKU, barkod, birim, KDV ve net fiyat kayıtları.</p>
        </div>
        <div class="page-actions">
            @can('inventory.view')
                <a href="{{ route('inventory.stock.index') }}" data-workspace-link>Stok Durumu</a>
                <a href="{{ route('inventory.warehouses.index') }}" data-workspace-link>Depolar</a>
            @endcan
            @if ($productFamilyVariantEnabled)
                <a href="{{ route('inventory.product-families.index') }}" data-workspace-link>Ürün Aileleri</a>
            @endif
            <a href="{{ route('inventory.categories.index') }}" data-workspace-link>Kategoriler</a>
            <a href="{{ route('inventory.units.index') }}" data-workspace-link>Birimler</a>
        </div>
    </section>

    <div class="list-layout">
        <form method="get" action="{{ route('inventory.index') }}" class="filters-panel">
            <div class="filters-panel-title">▼ Filtreler</div>
            <div class="filters-panel-body">
                <label>
                    Genel Arama
                    <input type="search" name="q" value="{{ $search }}" placeholder="Kod, ad veya barkod" data-dirty-ignore>
                </label>
                <label>
                    Durum
                    <select name="status" data-dirty-ignore>
                        <option value="all" @selected($statusFilter === 'all')>Tümü</option>
                        <option value="active" @selected($statusFilter === 'active')>Aktif</option>
                        <option value="inactive" @selected($statusFilter === 'inactive')>Pasif</option>
                    </select>
                </label>
            </div>
            <div class="filters-panel-actions">
                <a href="{{ route('inventory.index') }}" class="button-secondary">Temizle</a>
                <button type="submit" class="button-primary">Filtrele</button>
            </div>
        </form>

        <section class="table-card">
            <div class="table-toolbar">
                <span class="subtle">{{ $products->total() }} kayıt</span>
                <div class="grow"></div>
                @can('products.manage')
                    <a class="button-primary" href="{{ route('inventory.products.create') }}" data-workspace-link>＋ Yeni Ürün</a>
                @endcan
            </div>
            <div class="statement-table-card">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Ürün Kodu</th>
                        <th>Ürün Adı</th>
                        <th>Kategori</th>
                        <th>Birim</th>
                        <th>Birincil Barkod</th>
                        <th class="amount-cell">Net Satış</th>
                        <th class="amount-cell">Net Alış</th>
                        <th>KDV</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($products as $product)
                        @php($primaryBarcode = $product->barcodes->firstWhere('is_primary', true))
                        <tr>
                            <td><a href="{{ route('inventory.products.show', $product->getKey()) }}" data-workspace-link>{{ $product->code }}</a></td>
                            <td><a href="{{ route('inventory.products.show', $product->getKey()) }}" data-workspace-link>{{ $product->name }}</a></td>
                            <td>{{ $product->category?->name ?? '—' }}</td>
                            <td>{{ $product->unit->name }}</td>
                            <td>{{ $primaryBarcode?->barcode ?? '—' }}</td>
                            <td class="amount-cell">{{ $product->sale_price_net }}</td>
                            <td class="amount-cell">{{ $product->purchase_price_net }}</td>
                            <td>%{{ $product->tax->rate }}</td>
                            <td>{{ $product->statusEnum()->label() }}</td>
                            <td><a href="{{ route('inventory.products.show', $product->getKey()) }}" data-workspace-link>Detay ↗</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">Filtreye uygun ürün kaydı bulunamadı.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $products->links() }}
        </section>
    </div>
@endsection
