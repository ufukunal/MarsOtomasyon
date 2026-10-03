<section class="panel stack">
    <div class="form-row">
        <select wire:model.live="collection">
            @foreach($collections as $set)<option value="{{ $set }}">{{ $set }}</option>@endforeach
        </select>
        <input type="file" wire:model="image" accept=".jpg,.jpeg,.png,.webp">
        <button type="button" wire:click="upload">Yükle</button>
    </div>

    @if($usingFallback)
        <div class="alert alert-warning">Bu set boş; önizlemede Ortak görseller kullanılıyor. Sıralama/silme Ortak sekmesinden yapılır.</div>
    @endif

    <div class="image-grid" data-product-image-sorter>
        @forelse($images as $imageRow)
            <article class="image-card" draggable="{{ $usingFallback ? 'false' : 'true' }}" data-attachment-id="{{ $imageRow->id }}">
                <img class="product-image-preview" src="{{ route('products.images.show', ['product'=>$product->id,'attachment'=>$imageRow->id]) }}" alt="">
                <div class="image-meta">
                    @if($loop->first)<strong>Ana görsel</strong>@endif
                    <span>{{ $imageRow->original_name }}</span>
                </div>
                @unless($usingFallback)
                    <div class="form-row">
                        <span class="drag-handle" title="Sürükleyerek sırala">↕ Sürükle</span>
                        <button type="button" wire:click="move({{ $imageRow->id }}, 'up')">↑</button>
                        <button type="button" wire:click="move({{ $imageRow->id }}, 'down')">↓</button>
                        <button type="button" wire:click="delete({{ $imageRow->id }})">Sil</button>
                    </div>
                @endunless
            </article>
        @empty
            <p>Bu sette görsel yok. Kanal çözümlemesinde Ortak sete düşülür.</p>
        @endforelse
    </div>
</section>
