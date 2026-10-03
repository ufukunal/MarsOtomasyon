<section class="panel stack">
    <div class="form-row">
        <select wire:model.live="collection">
            @foreach($collections as $set)<option value="{{ $set }}">{{ $set }}</option>@endforeach
        </select>
        <input type="file" wire:model="image" accept=".jpg,.jpeg,.png,.webp">
        <button type="button" wire:click="upload">Yükle</button>
    </div>

    <div class="image-grid">
        @forelse($images as $imageRow)
            <article class="image-card">
                <div class="image-meta">
                    @if($loop->first)<strong>Ana görsel</strong>@endif
                    <span>{{ $imageRow->original_name }}</span>
                </div>
                <div class="form-row">
                    <button type="button" wire:click="move({{ $imageRow->id }}, 'up')">↑</button>
                    <button type="button" wire:click="move({{ $imageRow->id }}, 'down')">↓</button>
                    <button type="button" wire:click="delete({{ $imageRow->id }})">Sil</button>
                </div>
            </article>
        @empty
            <p>Bu sette görsel yok. Kanal çözümlemesinde Ortak sete düşülür.</p>
        @endforelse
    </div>
</section>
