@extends('layouts.app')

@section('title', 'Stok Hareketleri')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Stok</p>
            <h1>Stok Hareketleri</h1>
            <p>Append-only fiziksel stok ledger'ı. Kayıtlar silent update/delete ile değiştirilemez.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.stock.index') }}" data-workspace-link>Bakiyeler</a>
            <a href="{{ route('inventory.warehouses.index') }}" data-workspace-link>Depolar</a>
            @can('products.manage')
                <a class="button-primary" href="{{ route('inventory.stock.movements.create') }}" data-workspace-link>Yeni Stok Hareketi</a>
            @endcan
        </div>
    </section>

    <section class="detail-card statement-table-card">
        <table class="data-table">
            <thead>
            <tr>
                <th>Zaman</th>
                <th>Tip</th>
                <th>SKU</th>
                <th>Depo / Lokasyon</th>
                <th class="amount-cell">Miktar</th>
                <th class="amount-cell">Birim Maliyet</th>
                <th class="amount-cell">Değer Etkisi</th>
                <th class="amount-cell">Son Bakiye</th>
                <th>Not</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($movements as $movement)
                <tr>
                    <td>{{ $movement->occurred_at?->format('d.m.Y H:i:s') }}</td>
                    <td>{{ $movement->movement_type->label() }}</td>
                    <td>{{ $movement->product->code }}</td>
                    <td>{{ $movement->warehouse->code }} / {{ $movement->location->code }}</td>
                    <td class="amount-cell">{{ $movement->quantity_delta }}</td>
                    <td class="amount-cell">{{ $movement->unit_cost }}</td>
                    <td class="amount-cell">{{ $movement->value_delta }}</td>
                    <td class="amount-cell">{{ $movement->balance_quantity_after }}</td>
                    <td>{{ $movement->note ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">Stok hareketi bulunmuyor.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>

    {{ $movements->links() }}
@endsection
