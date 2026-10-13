<section class="panel stack v65-product-image-manager" aria-label="Ürün görsel yönetimi">
    <div class="v65-product-image-heading">
        <div>
            <strong>Ürün Görselleri</strong>
            <small>Görselleri yükleyin, kapak seçin veya sıralamayı değiştirin. İşlemler anında kaydedilir.</small>
        </div>
    </div>
    <div class="v65-product-image-upload">
        <label class="field">
            <span class="field-label">Görsel Grubu</span>
            <select wire:model.live="collection">
                @foreach($collections as $set)<option value="{{ $set }}">{{ $set }}</option>@endforeach
            </select>
        </label>
        <label class="field v65-product-image-file">
            <span class="field-label">Dosya (JPG, PNG, WebP)</span>
            <input type="file" wire:model="image" accept=".jpg,.jpeg,.png,.webp">
        </label>
        <button type="button" wire:click="upload" wire:loading.attr="disabled" wire:target="image,upload">Görsel Yükle</button>
    </div>
    @error('image')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    @error('collection')<p class="field-error" role="alert">{{ $message }}</p>@enderror

    @if($usingFallback)
        <div class="alert alert-warning">Bu görsel grubunda kayıt bulunmuyor. Ortak görseller gösteriliyor; sıralama ve silme işlemleri Ortak grubundan yapılır.</div>
    @endif

    <div class="image-grid v65-product-image-grid" data-product-image-sorter>
        @forelse($images as $imageRow)
            <article class="image-card v65-product-image-card" wire:key="product-image-{{ $imageRow->id }}" draggable="{{ $usingFallback ? 'false' : 'true' }}" data-attachment-id="{{ $imageRow->id }}">
                <img class="product-image-preview" src="{{ route('products.images.show', ['product' => $product->id, 'attachment' => $imageRow->id]) }}" alt="{{ $imageRow->original_name }}" loading="lazy">
                <div class="image-meta">
                    @if($loop->first)<strong class="v65-product-image-cover">Ana Görsel</strong>@endif
                    <span class="v65-product-image-name" title="{{ $imageRow->original_name }}">{{ $imageRow->original_name }}</span>
                </div>
                @unless($usingFallback)
                    <div class="v65-product-image-actions">
                        <div class="v65-product-image-action-row">
                            @unless($loop->first)
                                <button type="button" class="button-secondary" wire:click="setCover({{ $imageRow->id }})">Kapak Yap</button>
                            @endunless
                            <span class="drag-handle" title="Sürükleyerek sırala">↕ Sürükle</span>
                        </div>
                        <div class="v65-product-image-action-row">
                            <button type="button" class="button-secondary" wire:click="move({{ $imageRow->id }}, 'up')" @disabled($loop->first) aria-label="{{ $imageRow->original_name }} görselini yukarı taşı">↑ Yukarı</button>
                            <button type="button" class="button-secondary" wire:click="move({{ $imageRow->id }}, 'down')" @disabled($loop->last) aria-label="{{ $imageRow->original_name }} görselini aşağı taşı">↓ Aşağı</button>
                            <button type="button" class="button-danger" wire:click="delete({{ $imageRow->id }})" aria-label="{{ $imageRow->original_name }} görselini sil">Sil</button>
                        </div>
                    </div>
                @endunless
            </article>
        @empty
            <p class="v65-product-image-empty">Henüz görsel yok. JPG, PNG veya WebP yükleyin; ilk görsel kapak olur.</p>
        @endforelse
    </div>
</section>
