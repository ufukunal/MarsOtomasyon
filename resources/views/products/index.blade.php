@extends('layouts.app')

@section('title', 'Ürün/Stok')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Katalog</p>
            <h1>Ürünler</h1>
            <p>Aktif firmaya ait SKU, barkod, birim, KDV ve net fiyat kayıtları.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.stock.index') }}" data-workspace-link>Stok Bakiyeleri</a>
            <a href="{{ route('inventory.warehouses.index') }}" data-workspace-link>Depolar</a>
            <a href="{{ route('inventory.categories.index') }}" data-workspace-link>Kategoriler</a>
            <a href="{{ route('inventory.units.index') }}" data-workspace-link>Birimler</a>
            @can('products.manage')
                <a class="button-primary" href="{{ route('inventory.products.create') }}" data-workspace-link>Yeni Ürün</a>
            @endcan
        </div>
    </section>

    <form method="get" action="{{ route('inventory.index') }}" class="detail-card">
        <div class="form-grid">
            <label>
                Ara
                <input type="search" name="q" value="{{ $search }}" placeholder="Kod, ürün adı veya barkod" data-dirty-ignore>
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
        <div class="page-actions">
            <span></span>
            <button type="submit">Filtrele</button>
        </div>
    </form>

    <section class="detail-card statement-table-card">
        <table class="data-table">
            <thead>
            <tr>
                <th>Kod</th>
                <th>Ürün</th>
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
                    <td>{{ $product->code }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category?->name ?? '—' }}</td>
                    <td>{{ $product->unit->name }}</td>
                    <td>{{ $primaryBarcode?->barcode ?? '—' }}</td>
                    <td class="amount-cell">{{ $product->sale_price_net }}</td>
                    <td class="amount-cell">{{ $product->purchase_price_net }}</td>
                    <td>%{{ $product->tax->rate }}</td>
                    <td>{{ $product->statusEnum()->label() }}</td>
                    <td><a href="{{ route('inventory.products.show', $product->getKey()) }}" data-workspace-link>Detay</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">Filtreye uygun ürün kaydı bulunamadı.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>

    {{ $products->links() }}
@endsection
