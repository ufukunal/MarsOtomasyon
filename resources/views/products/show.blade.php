@extends('layouts.app')

@section('title', 'Ürün Detay')

@section('app-content')
    @php($primaryBarcode = $product->barcodes->firstWhere('is_primary', true))

    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün Detay</p>
            <h1>{{ $product->name }}</h1>
            <p>{{ $product->code }} · {{ $product->statusEnum()->label() }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.index') }}" data-workspace-link>Listeye Dön</a>
            @can('products.manage')
                <a class="button-primary" href="{{ route('inventory.products.edit', $product->getKey()) }}" data-workspace-link>Düzenle</a>
            @endcan
        </div>
    </section>

    <section class="detail-card">
        <h2>Ürün Kimliği</h2>
        <dl class="detail-list">
            <div><dt>SKU Kodu</dt><dd>{{ $product->code }}</dd></div>
            <div><dt>Ürün Adı</dt><dd>{{ $product->name }}</dd></div>
            <div><dt>Durum</dt><dd>{{ $product->statusEnum()->label() }}</dd></div>
            <div><dt>Kategori</dt><dd>{{ $product->category?->name ?? '—' }}</dd></div>
            <div><dt>Birim</dt><dd>{{ $product->unit->code }} · {{ $product->unit->name }}</dd></div>
        </dl>
    </section>

    <section class="detail-card">
        <h2>Fiyat / Vergi</h2>
        <dl class="detail-list">
            <div><dt>Net Satış Fiyatı</dt><dd>{{ $product->sale_price_net }}</dd></div>
            <div><dt>Net Alış Fiyatı</dt><dd>{{ $product->purchase_price_net }}</dd></div>
            <div><dt>Fiyat Modu</dt><dd>KDV Hariç / Net</dd></div>
            <div><dt>KDV / Vergi</dt><dd>%{{ $product->tax->rate }} · {{ $product->tax->name }}</dd></div>
        </dl>
    </section>

    <section class="detail-card">
        <h2>Barkodlar</h2>
        @if ($product->barcodes->isEmpty())
            <p>Barkod kaydı yok.</p>
        @else
            <dl class="detail-list">
                @if ($primaryBarcode)
                    <div><dt>Birincil Barkod</dt><dd>{{ $primaryBarcode->barcode }}</dd></div>
                @endif
                @foreach ($product->barcodes->where('is_primary', false) as $barcode)
                    <div><dt>Ek Barkod</dt><dd>{{ $barcode->barcode }}</dd></div>
                @endforeach
            </dl>
        @endif
    </section>
@endsection
