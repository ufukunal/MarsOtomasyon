@extends('layouts.app')

@section('title', 'Stok Bakiyeleri')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Stok</p>
            <h1>Stok Bakiyeleri</h1>
            <p>Fiziksel miktar ve moving-average taşıma maliyeti; authority append-only stok hareketleridir.</p>
        </div>
        <div class="page-actions">
            @can('products.view')
                <a href="{{ route('inventory.index') }}" data-workspace-link>Ürünler</a>
            @endcan
            <a href="{{ route('inventory.warehouses.index') }}" data-workspace-link>Depolar</a>
            <a href="{{ route('inventory.stock.movements') }}" data-workspace-link>Hareketler</a>
            @can('inventory.manage')
                <a class="button-primary" href="{{ route('inventory.stock.movements.create') }}" data-workspace-link>Yeni Stok Hareketi</a>
            @endcan
        </div>
    </section>

    <section class="detail-card statement-table-card">
        <table class="data-table">
            <thead>
            <tr>
                <th>SKU</th>
                <th>Ürün</th>
                <th>Depo</th>
                <th>Lokasyon</th>
                <th class="amount-cell">Fiziksel Miktar</th>
                <th class="amount-cell">Ort. Birim Maliyet</th>
                <th class="amount-cell">Stok Değeri</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($balances as $balance)
                <tr>
                    <td>{{ $balance->product->code }}</td>
                    <td>{{ $balance->product->name }}</td>
                    <td>{{ $balance->warehouse->name }}</td>
                    <td>{{ $balance->location->code }} — {{ $balance->location->name }}</td>
                    <td class="amount-cell">{{ $balance->quantity }}</td>
                    <td class="amount-cell">{{ $balance->average_unit_cost }}</td>
                    <td class="amount-cell">{{ $balance->inventory_value }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Pozitif fiziksel stok bakiyesi bulunmuyor.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>

    {{ $balances->links() }}
@endsection
