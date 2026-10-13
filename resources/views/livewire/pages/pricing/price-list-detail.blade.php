<div class="stack v65-list-page">
    <section class="panel">
        <h2>Fiyat Listesi</h2>
        <div class="form-grid">
            <x-field.text label="Ad" wire:model="name" />
            <x-field.text label="Para Birimi" wire:model="currency" />
            <x-field.toggle label="Giriş KDV Dahil" wire:model="vatIncluded" />
            <x-field.toggle label="Varsayılan" wire:model="isDefault" />
            <x-field.toggle label="Aktif" wire:model="isActive" />
        </div>
        <div class="form-actions"><button type="button" wire:click="saveList" wire:loading.attr="disabled" wire:target="saveList">Listeyi Kaydet</button></div>
    </section>

    @if($list)
        <section class="panel stack">
            <h2>Ürün Fiyatları</h2>
            <div class="form-grid">
                <label class="field"><span class="field-label">Ürün</span><select wire:model="productId"><option value="">Ürün Seçin</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>@endforeach</select></label>
                <label class="field"><span class="field-label">Fiyat</span><input wire:model="price" inputmode="decimal"></label>
                <label class="field"><span class="field-label">Geçerlilik Başlangıcı</span><input wire:model="validFrom" type="date"></label>
                <label class="field"><span class="field-label">Geçerlilik Bitişi</span><input wire:model="validTo" type="date"></label>
            </div>
            <div class="form-actions">
                <button type="button" wire:click="addItem" wire:loading.attr="disabled" wire:target="addItem">{{ $itemId ? 'Fiyatı Güncelle' : 'Fiyat Ekle' }}</button>
                @if($itemId)<button type="button" class="button-secondary" wire:click="cancelItemEdit">Vazgeç</button>@endif
            </div>
            <div class="form-row">
                <label class="field"><span class="field-label">Toplu Fiyat Değişimi (%)</span><input wire:model="bulkPercent" inputmode="decimal"></label>
                <button type="button" class="button-secondary" wire:click="bulkAdjust">Toplu Yüzde Uygula</button>
            </div>
            <div class="table-scroll v65-list-table">
                <table class="data-table"><thead><tr><th>Ürün</th><th>Fiyat</th><th>Başlangıç</th><th>Bitiş</th><th>İşlem</th></tr></thead><tbody>
                @forelse($items as $item)
                    <tr><td>@can('products.view')<a class="v65-record-link" href="{{ route('products.show', ['product' => $item->product_id]) }}">{{ $item->product->code }} · {{ $item->product->name }}</a>@else{{ $item->product->code }} · {{ $item->product->name }}@endcan</td><td>{{ \App\Support\Formatting\TrFormatter::money((string)$item->price,4) }}</td><td>{{ $item->valid_from?->format('d.m.Y') }}</td><td>{{ $item->valid_to?->format('d.m.Y') }}</td><td><button type="button" class="button-secondary" wire:click="editItem({{ $item->id }})">Düzenle</button></td></tr>
                @empty
                    <tr><td colspan="5" class="empty-state">Listeye henüz fiyat eklenmedi.</td></tr>
                @endforelse
                </tbody></table>
            </div>
        </section>
    @endif
    @foreach($errors->all() as $error)<div class="field-error" role="alert">{{ $error }}</div>@endforeach
</div>
