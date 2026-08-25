@extends('layouts.app')

@section('title', 'Stok Sayımları')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Stok</p>
            <h1>Stok Sayımları</h1>
            <p>Lokasyon snapshot'ı üzerinden fiziksel sayım yapın. Farklar yalnız finalize edildiğinde append-only stok hareketine dönüşür.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.stock.index') }}" data-workspace-link>Bakiyeler</a>
            <a href="{{ route('inventory.stock.movements') }}" data-workspace-link>Hareketler</a>
            @can('inventory.manage')
                <a class="button-primary" href="{{ route('inventory.counts.create') }}" data-workspace-link>Yeni Sayım</a>
            @endcan
        </div>
    </section>

    <section class="detail-card statement-table-card">
        <table class="data-table">
            <thead>
            <tr>
                <th>#</th>
                <th>Başlangıç</th>
                <th>Depo / Lokasyon</th>
                <th>Durum</th>
                <th>Final</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($counts as $count)
                <tr>
                    <td><a href="{{ route('inventory.counts.show', $count->getKey()) }}" data-workspace-link>#{{ $count->getKey() }}</a></td>
                    <td>{{ $count->started_at?->format('d.m.Y H:i:s') }}</td>
                    <td>{{ $count->warehouse->code }} / {{ $count->location->code }}</td>
                    <td>{{ $count->status === 'posted' ? 'Tamamlandı' : 'Taslak' }}</td>
                    <td>{{ $count->posted_at?->format('d.m.Y H:i:s') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Stok sayımı bulunmuyor.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    {{ $counts->links() }}
@endsection
