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
                <button type="button" wire:click="addAttribute">Özellik Ekle</button>
            </div>
            <div>@foreach($attributes as $attribute)<span class="badge">{{ $attribute->name }}</span>@endforeach</div>
        </section>

        <section class="panel stack">
            <select wire:model="productId"><option value="">Ürün seçin</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>@endforeach</select>
            @foreach($attributes as $attribute)
                <input wire:model="values.{{ $attribute->id }}" placeholder="{{ $attribute->name }}">
            @endforeach
            <button type="button" wire:click="attachProduct">Ürünü Gruba Bağla</button>
            @foreach($warnings as $warning)<div class="alert alert-warning">{{ $warning }}</div>@endforeach
        </section>

        <section class="panel">
            @if($groupProducts->count() === 1)
                <div class="alert alert-warning">Varyant grubu yalnız bir ürün içeriyor. Bu durum engellenmez ancak grup sunum açısından anlamlı değildir.</div>
            @endif
            <table class="data-table"><thead><tr><th>Kod</th><th>Ürün</th><th>Değerler</th></tr></thead><tbody>
            @foreach($groupProducts as $product)<tr><td>{{ $product->code }}</td><td>{{ $product->name }}</td><td>@foreach($product->variantValues as $value)<span class="badge">{{ $value->attribute->name }}: {{ $value->value }}</span>@endforeach</td></tr>@endforeach
            </tbody></table>
        </section>
    @endif
</div>
