@extends('layouts.app')

@section('title', $product ? 'Ürün Düzenle' : 'Yeni Ürün')

@section('app-content')
    @php($primaryBarcode = $product?->barcodes->firstWhere('is_primary', true)?->barcode)
    @php($additionalBarcodeText = $product?->barcodes->filter(fn ($barcode) => ! $barcode->is_primary)->pluck('barcode')->implode("\n") ?? '')
    @php($catalogReady = $units->isNotEmpty() && $taxes->isNotEmpty())

    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Katalog</p>
            <h1>{{ $product ? 'Ürün Düzenle' : 'Yeni Ürün' }}</h1>
            <p>{{ $product ? 'SKU kimliği, marka, fiyat, vergi ve barkod bilgilerini güncelleyin.' : 'Aktif firmaya yeni satılabilir / stoklanabilir SKU ekleyin.' }}</p>
        </div>
        <a href="{{ $product ? route('inventory.products.show', $product->getKey()) : route('inventory.index') }}" data-workspace-link>Vazgeç</a>
    </section>

    @if (! $catalogReady)
        <div class="notice-error" role="alert">
            <strong>Ürün kaydı için katalog tanımları eksik.</strong>
            <p>En az bir aktif birim ve bir aktif KDV/vergi tanımı olmadan ürün kaydedilemez.</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="notice-error" role="alert">
            <strong>Kayıt tamamlanamadı.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ $product ? route('inventory.products.update', $product->getKey()) : route('inventory.products.store') }}" class="detail-card">
        @csrf
        @if ($product)
            @method('PUT')
        @endif

        <h2>Ürün Kimliği</h2>
        <div class="form-grid">
            <label>
                Ürün / SKU Kodu
                <input name="code" maxlength="64" required value="{{ old('code', $product?->code) }}">
            </label>

            <label>
                Ürün Adı
                <input name="name" maxlength="200" required value="{{ old('name', $product?->name) }}">
            </label>

            <label>
                Marka
                <input name="brand" maxlength="160" value="{{ old('brand', $product?->brand) }}">
            </label>

            @if ($product)
                <label>
                    Durum
                    <select name="status" required>
                        @foreach ($productStatuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $product->statusEnum()->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label>
                Kategori
                <select name="category_id">
                    <option value="">Kategori Yok</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->getKey() }}" @selected((string) old('category_id', $product?->category_id ?? '') === (string) $category->getKey())>
                            {{ $category->code }} · {{ $category->name }}{{ $category->is_active ? '' : ' · Pasif' }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Birim
                <select name="unit_id" required>
                    <option value="">Birim Seçin</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->getKey() }}" @selected((string) old('unit_id', $product?->unit_id ?? '') === (string) $unit->getKey())>
                            {{ $unit->code }} · {{ $unit->name }}{{ $unit->is_active ? '' : ' · Pasif' }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                KDV / Vergi
                <select name="tax_id" required>
                    <option value="">Vergi Seçin</option>
                    @foreach ($taxes as $tax)
                        <option value="{{ $tax->getKey() }}" @selected((string) old('tax_id', $product?->tax_id ?? '') === (string) $tax->getKey())>
                            %{{ $tax->rate }} · {{ $tax->name }}{{ $tax->is_active ? '' : ' · Pasif' }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <h2>Net Fiyatlar</h2>
        <p>Ürün master fiyatları KDV hariç / net tutulur.</p>
        <div class="form-grid">
            <label>
                Net Satış Fiyatı
                <input type="number" name="sale_price_net" min="0" step="0.000001" required value="{{ old('sale_price_net', $product?->sale_price_net ?? '0') }}">
            </label>

            <label>
                Net Alış Fiyatı
                <input type="number" name="purchase_price_net" min="0" step="0.000001" required value="{{ old('purchase_price_net', $product?->purchase_price_net ?? '0') }}">
            </label>
        </div>

        <h2>Barkodlar</h2>
        <div class="form-grid">
            <label>
                Birincil Barkod
                <input name="primary_barcode" maxlength="128" value="{{ old('primary_barcode', $primaryBarcode) }}">
            </label>

            <label>
                Ek Barkodlar
                <textarea name="additional_barcodes" rows="6" maxlength="8000" placeholder="Her satıra bir barkod">{{ old('additional_barcodes', $additionalBarcodeText) }}</textarea>
            </label>
        </div>

        <div class="page-actions">
            <span></span>
            <button type="submit" @disabled(! $catalogReady)>Kaydet</button>
        </div>
    </form>
@endsection
