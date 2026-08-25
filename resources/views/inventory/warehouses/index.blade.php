@extends('layouts.app')

@section('title', 'Depolar ve Lokasyonlar')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Stok</p>
            <h1>Depolar ve Lokasyonlar</h1>
            <p>Fiziksel stok hareketlerinin company-scoped depo ve lokasyon master kayıtları.</p>
        </div>
        <div class="page-actions">
            @can('products.view')
                <a href="{{ route('inventory.index') }}" data-workspace-link>Ürünler</a>
            @endcan
            <a href="{{ route('inventory.stock.index') }}" data-workspace-link>Stok Bakiyeleri</a>
            <a href="{{ route('inventory.stock.movements') }}" data-workspace-link>Hareketler</a>
        </div>
    </section>

    @can('inventory.manage')
        <form method="post" action="{{ route('inventory.warehouses.store') }}" class="detail-card">
            @csrf
            <h2>Yeni Depo</h2>
            <div class="form-grid">
                <label>
                    Kod
                    <input type="text" name="code" maxlength="64" required>
                </label>
                <label>
                    Ad
                    <input type="text" name="name" maxlength="160" required>
                </label>
            </div>
            <div class="page-actions">
                <span></span>
                <button class="button-primary" type="submit">Depo Oluştur</button>
            </div>
        </form>
    @endcan

    @foreach ($warehouses as $warehouse)
        <section class="detail-card">
            <div class="workspace-hero">
                <div>
                    <p class="eyebrow">{{ $warehouse->code }}</p>
                    <h2>{{ $warehouse->name }}</h2>
                    <p>{{ $warehouse->is_active ? 'Aktif' : 'Pasif' }}</p>
                </div>
            </div>

            <div class="statement-table-card">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Lokasyon Kodu</th>
                        <th>Lokasyon</th>
                        <th>Durum</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($warehouse->locations as $location)
                        <tr>
                            <td>{{ $location->code }}</td>
                            <td>{{ $location->name }}</td>
                            <td>{{ $location->is_active ? 'Aktif' : 'Pasif' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Henüz lokasyon tanımlanmamış.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @can('inventory.manage')
                <form method="post" action="{{ route('inventory.warehouses.locations.store', $warehouse->getKey()) }}">
                    @csrf
                    <h3>Lokasyon Ekle</h3>
                    <div class="form-grid">
                        <label>
                            Kod
                            <input type="text" name="code" maxlength="64" required>
                        </label>
                        <label>
                            Ad
                            <input type="text" name="name" maxlength="160" required>
                        </label>
                    </div>
                    <div class="page-actions">
                        <span></span>
                        <button type="submit">Lokasyon Ekle</button>
                    </div>
                </form>
            @endcan
        </section>
    @endforeach
@endsection
