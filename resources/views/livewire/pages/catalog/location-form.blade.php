<form wire:submit="save" class="panel stack">
    <x-field.text label="Kod" wire:model="code" :disabled="(bool) $location" />
    <x-field.text label="Ad" wire:model="name" />
    <x-field.select label="Tip" wire:model.live="kind" :options="['warehouse'=>'Depo','branch'=>'Şube','vehicle'=>'Araç','subcontractor'=>'Fasoncu']" />
    @if($kind === 'vehicle')
        <x-field.text label="Plaka" wire:model="plate" />
    @elseif($kind === 'subcontractor')
        <label class="field">
            <span class="field-label">Fasoncu Cari</span>
            <select wire:model="subcontractorContactId">
                <option value="">Seçin</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
    @endif
    <x-field.textarea label="Adres" wire:model="address" />
    <x-field.toggle label="Varsayılan" wire:model="isDefault" />
    <x-field.toggle label="Aktif" wire:model="isActive" />
    <button type="submit">Kaydet</button>
</form>
