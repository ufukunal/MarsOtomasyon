@extends('layouts.app')

@section('title', 'Kategoriler')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Katalog</p>
            <h1>Kategoriler</h1>
            <p>Ürün sınıflandırması için firma bazlı kategori master kayıtları.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.index') }}" data-workspace-link>Ürünler</a>
            <a href="{{ route('inventory.units.index') }}" data-workspace-link>Birimler</a>
            @can('products.manage')
                <a class="button-primary" href="{{ route('inventory.categories.create') }}" data-workspace-link>Yeni Kategori</a>
            @endcan
        </div>
    </section>

    <form method="get" action="{{ route('inventory.categories.index') }}" class="detail-card">
        <div class="form-grid">
            <label>
                Ara
                <input type="search" name="q" value="{{ $search }}" placeholder="Kod veya kategori adı" data-dirty-ignore>
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
                <th>Kategori</th>
                <th>Ürün Sayısı</th>
                <th>Durum</th>
                <th>İşlem</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td>{{ $category->code }}</td>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->products_count }}</td>
                    <td>{{ $category->is_active ? 'Aktif' : 'Pasif' }}</td>
                    <td>
                        @can('products.manage')
                            <a href="{{ route('inventory.categories.edit', $category->getKey()) }}" data-workspace-link>Düzenle</a>
                        @else
                            —
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Filtreye uygun kategori bulunamadı.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    {{ $categories->links() }}
@endsection
