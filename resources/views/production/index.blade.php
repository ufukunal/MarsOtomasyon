@extends('layouts.app')

@section('title', 'Üretim')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">M14 / Üretim</p>
        <h1>Üretim</h1>
        <p>Reçete, üretim emri, stok tüketimi, fire/eksik, mamul girişi ve taşıma maliyeti tek lifecycle üzerinde.</p>
    </div>
    <div class="page-actions">
        <a class="button-primary" href="{{ route('production.report') }}">Üretim Raporu</a>
    </div>
</section>

@if ($errors->any())
    <section class="notice-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>
@endif

@can('production.manage')
<section class="detail-card">
    <h2>Yeni Reçete</h2>
    <form method="POST" action="{{ route('production.recipes.store') }}" class="form-grid">
        @csrf
        <div><label>Mamul</label><select name="product_id" required><option value="">Seçin</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>@endforeach</select></div>
        <div><label>Reçete Kodu</label><input name="code" required maxlength="64"></div>
        <div><label>Ad</label><input name="name" required maxlength="160"></div>
        <div><label>Batch Çıktı Miktarı</label><input name="output_quantity" type="number" min="0.000001" step="0.000001" required></div>
        <div class="form-grid" style="grid-column:1/-1">
            @for($i = 0; $i < 4; $i++)
                <div><label>Malzeme {{ $i + 1 }}</label><select name="materials[{{ $i }}][product_id]"><option value="">Seçin</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>@endforeach</select></div>
                <div><label>Miktar {{ $i + 1 }}</label><input name="materials[{{ $i }}][quantity]" type="number" min="0.000001" step="0.000001"></div>
            @endfor
        </div>
        <div style="grid-column:1/-1"><label>Not</label><textarea name="note" maxlength="500"></textarea></div>
        <div class="page-actions"><button class="button-primary" type="submit">Reçete Oluştur</button></div>
    </form>
</section>

<section class="detail-card">
    <h2>Yeni Üretim Emri</h2>
    <form method="POST" action="{{ route('production.orders.store') }}" class="form-grid">
        @csrf
        <div><label>Reçete</label><select name="recipe_id" required><option value="">Seçin</option>@foreach($recipes->where('is_active', true) as $recipe)<option value="{{ $recipe->id }}">{{ $recipe->code }} — {{ $recipe->name }}</option>@endforeach</select></div>
        <div><label>Emir No</label><input name="order_no" required maxlength="64"></div>
        <div><label>Planlanan Miktar</label><input name="planned_quantity" type="number" min="0.000001" step="0.000001" required></div>
        <div><label>Depo</label><select name="warehouse_id" required><option value="">Seçin</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach</select></div>
        <div><label>Lokasyon</label><select name="location_id" required><option value="">Seçin</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->code }} — {{ $location->name }}</option>@endforeach</select></div>
        <div><label>Not</label><input name="note" maxlength="500"></div>
        <div class="page-actions"><button class="button-primary" type="submit">Emir Oluştur</button></div>
    </form>
</section>
@endcan

<section class="detail-card">
    <h2>Reçeteler</h2>
    <div class="statement-table-card"><table class="data-table"><thead><tr><th>Kod</th><th>Ad</th><th>Mamul</th><th>Batch</th><th>Durum</th></tr></thead><tbody>
    @forelse($recipes as $recipe)
        <tr><td>{{ $recipe->code }}</td><td>{{ $recipe->name }}</td><td>{{ $recipe->product?->code }} — {{ $recipe->product?->name }}</td><td>{{ $recipe->output_quantity }}</td><td>{{ $recipe->is_active ? 'Aktif' : 'Pasif' }}</td></tr>
    @empty<tr><td colspan="5">Reçete yok.</td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="detail-card">
    <h2>Üretim Emirleri</h2>
    <div class="statement-table-card"><table class="data-table"><thead><tr><th>Emir</th><th>Mamul</th><th>Depo</th><th>Plan</th><th>Durum</th><th>Maliyet</th><th></th></tr></thead><tbody>
    @forelse($orders as $order)
        <tr><td>{{ $order->order_no }}</td><td>{{ $order->product?->code }} — {{ $order->product?->name }}</td><td>{{ $order->warehouse?->code }}</td><td>{{ $order->planned_quantity }}</td><td>{{ $order->status }}</td><td>{{ number_format((float) $order->output_value, 2, ',', '.') }}</td><td><a href="{{ route('production.show', $order->id) }}">Aç</a></td></tr>
    @empty<tr><td colspan="7">Üretim emri yok.</td></tr>@endforelse
    </tbody></table></div>
    {{ $orders->links() }}
</section>
@endsection
