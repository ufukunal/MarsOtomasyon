@extends('layouts.app')

@section('title', 'Birimler')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Katalog</p>
            <h1>Birimler</h1>
            <p>Ürün miktar authority'si için firma bazlı temel ölçü / sayım birimleri.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.index') }}" data-workspace-link>Ürünler</a>
            <a href="{{ route('inventory.categories.index') }}" data-workspace-link>Kategoriler</a>
            @can('products.manage')
                <a class="button-primary" href="{{ route('inventory.units.create') }}" data-workspace-link>Yeni Birim</a>
            @endcan
        </div>
    </section>

    <form method="get" action="{{ route('inventory.units.index') }}" class="detail-card">
        <div class="form-grid">
            <label>
                Ara
                <input type="search" name="q" value="{{ $search }}" placeholder="Kod veya birim adı" data-dirty-ignore>
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
                <th>Birim</th>
                <th>Ürün Sayısı</th>
                <th>Durum</th>
                <th>İşlem</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($units as $unit)
                <tr>
                    <td>{{ $unit->code }}</td>
                    <td>{{ $unit->name }}</td>
                    <td>{{ $unit->products_count }}</td>
                    <td>{{ $unit->is_active ? 'Aktif' : 'Pasif' }}</td>
                    <td>
                        @can('products.manage')
                            <a href="{{ route('inventory.units.edit', $unit->getKey()) }}" data-workspace-link>Düzenle</a>
                        @else
                            —
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Filtreye uygun birim bulunamadı.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    {{ $units->links() }}
@endsection
