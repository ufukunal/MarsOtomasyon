<div class="stack v65-catalog-form">
    <nav class="v65-product-edit-navigation" aria-label="Marka gezinme">
        <a href="{{ route('brands.index') }}">← Marka Listesi</a>
    </nav>
    <section class="panel form-grid v65-product-edit-card" aria-label="{{ $brand ? 'Marka düzenleme' : 'Yeni marka' }}">
        <x-field.text label="Marka Adı" wire:model="name" />
        <x-field.toggle label="Aktif" wire:model="isActive" />
    </section>
    @error('name')<div class="field-error" role="alert">{{ $message }}</div>@enderror
    <x-v65.form-actions align="start" class="v65-product-edit-savebar">
        <x-v65.button wire:click="save" wire:loading.attr="disabled" wire:target="save">{{ $brand ? 'Değişiklikleri Kaydet' : 'Markayı Kaydet' }}</x-v65.button>
        <x-v65.button variant="secondary" :href="route('brands.index')">Listeye Dön</x-v65.button>
    </x-v65.form-actions>
</div>
