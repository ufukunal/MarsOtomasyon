@extends('layouts.app')

@section('title', 'Stok Sayımı #'.$stockCount->getKey())

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Stok</p>
            <h1>Stok Sayımı #{{ $stockCount->getKey() }}</h1>
            <p>{{ $stockCount->warehouse->code }} / {{ $stockCount->location->code }} — {{ $stockCount->status === 'posted' ? 'Tamamlandı' : 'Taslak' }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.counts.index') }}" data-workspace-link>Sayım Listesi</a>
            <a href="{{ route('inventory.stock.index') }}" data-workspace-link>Bakiyeler</a>
            <a href="{{ route('inventory.stock.movements') }}" data-workspace-link>Hareketler</a>
        </div>
    </section>

    @if ($errors->any())
        <div class="form-errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if ($stockCount->status === 'draft')
        @can('inventory.manage')
            <section class="detail-grid">
                <form method="post" action="{{ route('inventory.counts.scan', $stockCount->getKey()) }}" class="detail-card">
                    @csrf
                    <h2>Hızlı Sayım</h2>
                    <p>Barkod okutulduğunda sayılan miktar mevcut satıra eklenir. Bu işlem stok hareketi oluşturmaz.</p>
                    <div class="form-grid">
                        <label>
                            Barkod
                            <input name="barcode" type="text" maxlength="128" autocomplete="off" autofocus required>
                        </label>
                        <label>
                            Miktar
                            <input name="quantity" type="number" min="0.000001" step="0.000001" value="1">
                        </label>
                    </div>
                    <div class="page-actions"><span></span><button class="button-primary" type="submit">Barkodu Say</button></div>
                </form>

                <form method="post" action="{{ route('inventory.counts.line.update', $stockCount->getKey()) }}" class="detail-card">
                    @csrf
                    @method('PUT')
                    <h2>Manuel Sayım Satırı</h2>
                    <div class="form-grid">
                        <label>
                            Ürün
                            <select name="product_id" required>
                                <option value="">Seçiniz</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->getKey() }}">{{ $product->code }} — {{ $product->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Sayılan Miktar
                            <input name="counted_quantity" type="number" min="0" step="0.000001" required>
                        </label>
                        <label>
                            Değerleme Birim Maliyeti
                            <input name="valuation_unit_cost" type="number" min="0.000001" step="0.000001">
                            <small>Snapshot'ta maliyeti olmayan pozitif fark için zorunludur.</small>
                        </label>
                    </div>
                    <div class="page-actions"><span></span><button class="button-primary" type="submit">Satırı Güncelle</button></div>
                </form>
            </section>
        @endcan
    @endif

    <section class="detail-card statement-table-card">
        <table class="data-table">
            <thead>
            <tr>
                <th>SKU</th>
                <th>Ürün</th>
                <th class="amount-cell">Snapshot</th>
                <th class="amount-cell">Sayılan</th>
                <th class="amount-cell">Fark</th>
                <th class="amount-cell">Snapshot Maliyet</th>
                <th class="amount-cell">Değerleme</th>
                <th>Adjustment</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($stockCount->lines as $line)
                <tr>
                    <td>{{ $line->product->code }}</td>
                    <td>{{ $line->product->name }}</td>
                    <td class="amount-cell">{{ $line->expected_quantity }}</td>
                    <td class="amount-cell">{{ $line->counted_quantity }}</td>
                    <td class="amount-cell">{{ $line->variance_quantity }}</td>
                    <td class="amount-cell">{{ $line->expected_unit_cost }}</td>
                    <td class="amount-cell">{{ $line->valuation_unit_cost ?? '—' }}</td>
                    <td>{{ $line->adjustment_movement_id ? '#'.$line->adjustment_movement_id : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">Snapshot'ta stok yok. Barkod veya manuel satır ile sayım ekleyebilirsiniz.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    @if ($stockCount->status === 'draft')
        @can('inventory.manage')
            <form method="post" action="{{ route('inventory.counts.post', $stockCount->getKey()) }}" class="detail-card">
                @csrf
                <h2>Sayımı Finalize Et</h2>
                <p>Finalize öncesi snapshot ile güncel fiziksel stok tekrar karşılaştırılır. Arada stok hareketi oluştuysa işlem atomik olarak reddedilir.</p>
                <div class="page-actions"><span></span><button class="button-primary" type="submit">Farkları İşle ve Sayımı Tamamla</button></div>
            </form>
        @endcan
    @else
        <section class="detail-card">
            <strong>Finalize:</strong> {{ $stockCount->posted_at?->format('d.m.Y H:i:s') }}
        </section>
    @endif
@endsection
