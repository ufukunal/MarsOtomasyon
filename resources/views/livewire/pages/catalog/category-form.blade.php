<section class="panel form-grid v65-workspace-form">
    <x-field.text label="Ad" wire:model="name" />
    <label class="field"><span class="field-label">Üst kategori</span><select wire:model="parentId"><option value="">Kök</option>@foreach($categories as $row)<option value="{{ $row->id }}">{{ $row->name }}</option>@endforeach</select></label>
    <x-field.number label="Sıra" kind="quantity" wire:model="sortOrder" />
    <x-field.toggle label="Aktif" wire:model="isActive" />
    <x-v65.form-actions class="v65-form-footer"><x-v65.button wire:click="save" wire:loading.attr="disabled" wire:target="save">Kaydet</x-v65.button></x-v65.form-actions>
</section>
