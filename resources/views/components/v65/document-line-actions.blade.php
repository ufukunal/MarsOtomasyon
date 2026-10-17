@props(['index'])
<div class="v65-reference-line-actions" aria-label="Satır {{ $index + 1 }} işlemleri">
    <details class="v65-reference-line-menu">
        <summary aria-label="Satır {{ $index + 1 }} detayını aç" title="Satır detayı">⌄</summary>
        <div class="v65-reference-line-popover">
            {{ $slot }}
        </div>
    </details>
    <button type="button" class="v65-reference-line-delete" wire:click="removeLine({{ $index }})"
        wire:loading.attr="disabled" wire:target="removeLine" aria-label="Satır {{ $index + 1 }} sil" title="Satırı sil">×</button>
</div>
