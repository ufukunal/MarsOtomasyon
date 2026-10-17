@props(['index'])
<div class="v65-reference-line-actions" aria-label="Satır {{ $index + 1 }} işlemleri">
    <button type="button" class="v65-reference-line-menu-button"
        aria-label="Satır {{ $index + 1 }} detaylarını aç" title="Satır detayları"
        data-v65-modal-open="v65-document-line-{{ $index }}">⌄</button>
    <x-v65.modal :id="'v65-document-line-'.$index" title="Satır Detayları" size="sm">
        <div class="v65-reference-line-detail-fields">{{ $slot }}</div>
        <x-slot:footer>
            <x-v65.button variant="secondary" data-v65-modal-close>Kapat</x-v65.button>
        </x-slot:footer>
    </x-v65.modal>
    <button type="button" class="v65-reference-line-delete" wire:click="removeLine({{ $index }})"
        wire:loading.attr="disabled" wire:target="removeLine" aria-label="Satır {{ $index + 1 }} sil" title="Satırı sil">×</button>
</div>
