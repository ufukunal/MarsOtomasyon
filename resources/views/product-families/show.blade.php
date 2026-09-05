@extends('layouts.app')

@section('title', $family->name)

@section('app-content')
<div class="page-header">
    <div><h1>{{ $family->code }} · {{ $family->name }}</h1><p>Stock/barcode/price identity SKU Product kayıtlarında kalır.</p></div>
    @can('products.manage')<a class="button-secondary" href="{{ route('inventory.product-families.edit', $family) }}">Düzenle</a>@endcan
</div>
@if ($errors->any())<div class="notice-error">{{ $errors->first() }}</div>@endif

<div class="panel">
    <h2>Varyant SKU'lar</h2>
    <div class="table-wrap"><table><thead><tr><th>SKU</th><th>Ürün</th><th>Seçimler</th></tr></thead><tbody>
    @forelse($family->variants as $variant)
        <tr><td><a href="{{ route('inventory.products.show', $variant->product_id) }}">{{ $variant->product?->code }}</a></td><td>{{ $variant->product?->name }}</td><td>{{ $variant->assignments->map(fn($a) => $a->value?->label)->filter()->join(' · ') }}</td></tr>
    @empty<tr><td colspan="3">Henüz varyant yok.</td></tr>@endforelse
    </tbody></table></div>
</div>

<div class="panel">
    <h2>Boyutlar ve değerler</h2>
    @foreach($family->dimensions as $dimension)
        <div><strong>{{ $dimension->name }} ({{ $dimension->code }})</strong>: {{ $dimension->values->pluck('label')->join(', ') ?: 'değer yok' }}</div>
        @can('products.manage')
        <form method="POST" action="{{ route('inventory.product-families.values.store', [$family, $dimension]) }}" class="form-inline">@csrf
            <input name="code" required maxlength="64" placeholder="değer kodu"><input name="label" required maxlength="120" placeholder="etiket"><button type="submit">Değer ekle</button>
        </form>
        @endcan
    @endforeach
    @can('products.manage')
    <form method="POST" action="{{ route('inventory.product-families.dimensions.store', $family) }}" class="form-inline">@csrf
        <input name="code" required maxlength="64" placeholder="boyut kodu"><input name="name" required maxlength="120" placeholder="boyut adı"><button type="submit">Boyut ekle</button>
    </form>
    @endcan
</div>

@can('products.manage')
<div class="panel">
    <h2>SKU'yu varyant olarak bağla</h2>
    <form method="POST" action="{{ route('inventory.product-families.variants.store', $family) }}">@csrf
        <label>Ürün<select name="product_id" required><option value="">Seçin</option>@foreach($simpleProducts as $product)<option value="{{ $product->getKey() }}">{{ $product->code }} · {{ $product->name }}</option>@endforeach</select></label>
        @foreach($family->dimensions as $dimension)
            <label>{{ $dimension->name }}<select name="dimension_values[{{ $dimension->getKey() }}]" required><option value="">Seçin</option>@foreach($dimension->values as $value)<option value="{{ $value->getKey() }}">{{ $value->label }}</option>@endforeach</select></label>
        @endforeach
        <button type="submit" class="button-primary">Varyant olarak bağla</button>
    </form>
</div>
@endcan

<div class="panel">
    <h2>Medya</h2>
    <p>Kapak: {{ $hero?->fileAsset?->original_name ?? 'placeholder' }}</p>
    <ul>@foreach($media as $attachment)<li>{{ $attachment->fileAsset?->original_name }} @if($hero?->getKey() === $attachment->getKey()) <strong>kapak</strong> @endif
        @can('products.manage')
        <form method="POST" action="{{ route('inventory.product-families.media.hero', [$family, $attachment]) }}" style="display:inline">@csrf<button type="submit">Kapak yap</button></form>
        <form method="POST" action="{{ route('inventory.product-families.media.detach', [$family, $attachment]) }}" style="display:inline">@csrf<button type="submit">Kaldır</button></form>
        @endcan
    </li>@endforeach</ul>
    @can('products.manage')<form method="POST" action="{{ route('inventory.product-families.media.store', $family) }}" class="form-inline">@csrf<input name="file_asset_id" type="number" min="1" required placeholder="FileAsset ID"><input name="label" maxlength="160" placeholder="etiket"><button type="submit">Mevcut varlığı bağla</button></form>@endcan
</div>

<div class="panel">
    <h2>Marketplace parent mapping</h2>
    <ul>@foreach($family->channelMappings as $mapping)<li>{{ $mapping->provider }} · {{ $mapping->external_parent_id }} · {{ $mapping->status }}</li>@endforeach</ul>
    @can('products.manage')<form method="POST" action="{{ route('inventory.product-families.channel-mappings.store', $family) }}">@csrf
        <label>Bağlantı<select name="connection_id" required><option value="">Seçin</option>@foreach($connections as $connection)<option value="{{ $connection->id }}" data-provider="{{ $connection->provider }}">{{ $connection->provider }} · {{ $connection->name }}</option>@endforeach</select></label>
        <label>Provider<input name="provider" required maxlength="64"></label><label>External parent ID<input name="external_parent_id" required maxlength="192"></label>
        <button type="submit">Parent mapping kaydet</button>
    </form>@endcan
</div>

@can('products.manage')
<form method="POST" action="{{ route('inventory.product-families.destroy', $family) }}" onsubmit="return confirm('Aile silinecek; SKU ürünleri korunacak. Devam?')">@csrf @method('DELETE')<button class="button-danger" type="submit">Aileyi sil</button></form>
@endcan
@endsection
