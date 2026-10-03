<div class="stack">
    <section class="panel form-row">
        <input wire:model="name" placeholder="Grup adı">
        <label><input type="checkbox" wire:model="isActive"> Aktif</label>
        <button type="button" wire:click="saveGroup">Kaydet</button>
    </section>

    @if($group)
        <section class="panel stack">
            <div class="form-row">
                <input wire:model="newAttribute" placeholder="Özellik: Renk, Ölçü…">
                <button type="button" wire:click="saveAttribute">{{ $attributeId ? 'Özelliği Güncelle' : 'Özellik Ekle' }}</button>
            </div>
            <div>
                @foreach($attributes as $attribute)
                    <button type="button" class="badge" wire:click="editAttribute({{ $attribute->id }})">{{ $attribute->name }}</button>
                @endforeach
            </div>
        </section>

        <section class="panel stack">
            <x-field.lookup label="Ürün" wire:model="productId" :model="\App\Models\Period\Product::class" label-field="name" code-field="code" barcode-field="barcode" />
            @foreach($attributes as $attribute)
                <input wire:model="values.{{ $attribute->id }}" placeholder="{{ $attribute->name }}">
            @endforeach
            <button type="button" wire:click="attachProduct">Varyant Değerlerini Kaydet</button>
            @foreach($warnings as $warning)<div class="alert alert-warning">{{ $warning }}</div>@endforeach
        </section>

        <section class="panel">
            @if($groupProducts->count() === 1)
                <div class="alert alert-warning">Varyant grubu yalnız bir ürün içeriyor. Bu durum engellenmez ancak grup sunum açısından anlamlı değildir.</div>
            @endif
            <table class="data-table">
                <thead><tr><th>Kod</th><th>Ürün</th><th>Değerler</th><th>Fiyat</th><th>Stok</th><th>Durum</th></tr></thead>
                <tbody>
                @foreach($groupProducts as $product)
                    <tr>
                        <td>{{ $product->code }}</td>
                        <td>{{ $product->name }}</td>
                        <td>@foreach($product->variantValues as $value)<span class="badge">{{ $value->attribute->name }}: {{ $value->value }}</span>@endforeach</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::money((string)$product->list_price) }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::quantity($product->availableQuantity()) }}</td>
                        <td>{{ $product->is_active ? 'Aktif' : 'Pasif' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
</div>
