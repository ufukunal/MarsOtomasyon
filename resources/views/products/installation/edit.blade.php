@extends('layouts.app')

@section('title', 'Kurulum PDF Builder')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Ürün / Kurulum PDF</p>
            <h1>{{ $product->name }}</h1>
            <p>{{ $product->code }} · Adımlar, uyarılar, araçlar, parçalar ve ürün görsellerinden versiyonlu A4 kurulum PDF'i.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('inventory.products.resources.edit', $product->getKey()) }}" data-workspace-link>Ürün Kaynakları</a>
            @if ($guide)
                <a href="{{ route('inventory.products.installation.preview', $product->getKey()) }}" target="_blank">A4 Önizleme</a>
            @endif
        </div>
    </section>

    <section class="detail-card">
        <h2>Kurulum Rehberi Taslağı</h2>
        <p class="field-hint">Liste alanlarında her satır ayrı bir öğedir. Taslak değişebilir; yayınlanan PDF versiyonları immutable kalır.</p>
        @can('products.manage')
            <form method="post" action="{{ route('inventory.products.installation.update', $product->getKey()) }}">
                @csrf
                @method('PUT')
                <label>Başlık
                    <input type="text" name="title" maxlength="160" required value="{{ old('title', $guide?->title ?? ($product->name.' Kurulum Rehberi')) }}">
                </label>
                <label>Giriş
                    <textarea name="intro" rows="4" maxlength="10000">{{ old('intro', $guide?->intro) }}</textarea>
                </label>
                <div class="form-grid">
                    <label>Kurulum Adımları
                        <textarea name="steps_text" rows="10" placeholder="Her satıra bir adım">{{ old('steps_text', $guide ? implode("\n", $guide->steps) : '') }}</textarea>
                    </label>
                    <label>Uyarılar
                        <textarea name="warnings_text" rows="10" placeholder="Her satıra bir güvenlik/uygulama uyarısı">{{ old('warnings_text', $guide ? implode("\n", $guide->warnings) : '') }}</textarea>
                    </label>
                    <label>Gerekli Araçlar
                        <textarea name="tools_text" rows="8" placeholder="Her satıra bir araç">{{ old('tools_text', $guide ? implode("\n", $guide->tools) : '') }}</textarea>
                    </label>
                    <label>Parçalar / Sarf Malzemeleri
                        <textarea name="parts_text" rows="8" placeholder="Her satıra bir parça">{{ old('parts_text', $guide ? implode("\n", $guide->parts) : '') }}</textarea>
                    </label>
                </div>

                <h3>PDF Görselleri</h3>
                @php($selectedImageIds = old('image_ids', $guide?->image_product_file_ids ?? []))
                @forelse ($images as $image)
                    <label class="checkbox-row">
                        <input type="checkbox" name="image_ids[]" value="{{ $image->getKey() }}" @checked(in_array($image->getKey(), $selectedImageIds, true))>
                        {{ $image->attachment->label ?? $image->attachment->fileAsset->original_name }}
                        {{ $image->is_main ? ' · Ana görsel' : '' }}
                    </label>
                @empty
                    <p>Aktif ürün görseli yok. Görseller Ürün Kaynakları ekranından yüklenebilir.</p>
                @endforelse

                <div class="page-actions"><span></span><button type="submit">Taslağı Kaydet</button></div>
            </form>
        @else
            <p>Bu rehberi düzenlemek için products.manage yetkisi gerekir.</p>
        @endcan
    </section>

    @if ($guide)
        <section class="detail-card">
            <h2>Yayın</h2>
            <p>Mevcut taslak revizyonu: <strong>{{ $guide->content_revision }}</strong>. Aynı içerik tekrar yayınlanırsa mevcut versiyon döner; içerik değişirse yeni immutable versiyon oluşur.</p>
            <div class="page-actions">
                <a href="{{ route('inventory.products.installation.preview', $product->getKey()) }}" target="_blank">A4 Önizleme</a>
                @can('products.manage')
                    <form method="post" action="{{ route('inventory.products.installation.publish', $product->getKey()) }}" class="inline-form">
                        @csrf
                        <button type="submit">Yeni Versiyonu Yayınla</button>
                    </form>
                @endcan
            </div>
        </section>
    @endif

    <section class="detail-card">
        <h2>Yayınlanmış Versiyonlar</h2>
        <table class="data-table">
            <thead><tr><th>Versiyon</th><th>Renderer</th><th>SHA-256</th><th>Üretim</th><th>İşlem</th></tr></thead>
            <tbody>
            @forelse ($documents as $document)
                <tr>
                    <td>v{{ $document->version }}</td>
                    <td>{{ $document->renderer_version }}</td>
                    <td><code>{{ $document->pdf_sha256 }}</code></td>
                    <td>{{ $document->generated_at?->format('d.m.Y H:i') }}</td>
                    <td><a href="{{ route('inventory.products.installation.download', [$product->getKey(), $document->version]) }}">PDF İndir</a></td>
                </tr>
            @empty
                <tr><td colspan="5">Henüz yayınlanmış kurulum PDF'i yok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection