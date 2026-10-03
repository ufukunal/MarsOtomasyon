<div class="stack">
    <section class="panel form-grid">
        <x-field.text label="Kod" wire:model="code" :disabled="(bool)$unit" />
        <x-field.text label="Ad" wire:model="name" />
        <x-field.toggle label="Temel birim" wire:model="isBase" />
        <x-field.toggle label="Aktif" wire:model="isActive" />
        <button type="button" wire:click="save">Kaydet</button>
    </section>
    @if($unit)
        <section class="panel stack">
            <h2>Dönüşümler</h2>
            <div class="form-row"><select wire:model="toUnitId"><option value="">Hedef birim</option>@foreach($units as $u)<option value="{{ $u->id }}">{{ $u->code }}</option>@endforeach</select><input wire:model="factor" inputmode="decimal"><button type="button" wire:click="addConversion">Ekle</button></div>
            <table class="data-table"><thead><tr><th>Hedef</th><th>Katsayı</th><th>Ters</th></tr></thead><tbody>@foreach($conversions as $row)<tr><td>{{ $row->toUnit->code }}</td><td>{{ $row->factor }}</td><td>{{ bcdiv('1',(string)$row->factor,6) }}</td></tr>@endforeach</tbody></table>
        </section>
    @endif
</div>
