<div class="stack">
    <section class="panel form-grid">
        <x-field.text label="Ad" wire:model="name" />
        <x-field.text label="Para Birimi" wire:model="currency" />
        <x-field.toggle label="Giriş KDV Dahil" wire:model="vatIncluded" />
        <x-field.toggle label="Varsayılan" wire:model="isDefault" />
        <x-field.toggle label="Aktif" wire:model="isActive" />
        <button type="button" wire:click="saveList">Listeyi Kaydet</button>
    </section>

    @if($list)
        <section class="panel stack">
            <div class="form-row">
                <select wire:model="productId"><option value="">Ürün</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>@endforeach</select>
                <input wire:model="price" inputmode="decimal" placeholder="Fiyat">
                <input wire:model="validFrom" type="date">
                <input wire:model="validTo" type="date">
                <button type="button" wire:click="addItem">{{ $itemId ? 'Fiyatı Güncelle' : 'Fiyat Ekle' }}</button>
                @if($itemId)<button type="button" wire:click="cancelItemEdit">Vazgeç</button>@endif
            </div>
            <div class="form-row">
                <input wire:model="bulkPercent" inputmode="decimal" placeholder="%">
                <button type="button" wire:click="bulkAdjust">Toplu Yüzde Uygula</button>
            </div>
            <table class="data-table"><thead><tr><th>Ürün</th><th>Fiyat</th><th>Başlangıç</th><th>Bitiş</th><th></th></tr></thead><tbody>
            @foreach($items as $item)<tr><td>{{ $item->product->code }} · {{ $item->product->name }}</td><td>{{ \App\Support\Formatting\TrFormatter::money((string)$item->price,4) }}</td><td>{{ $item->valid_from?->format('d.m.Y') }}</td><td>{{ $item->valid_to?->format('d.m.Y') }}</td><td><button type="button" wire:click="editItem({{ $item->id }})">Düzenle</button></td></tr>@endforeach
            </tbody></table>
        </section>
    @endif
</div>
