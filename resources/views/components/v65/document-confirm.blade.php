@props(['id', 'title', 'label', 'action', 'message', 'document' => null, 'variant' => 'primary', 'dirty' => false])
@php
    $allowedActions = ['post', 'reverse', 'confirm', 'approve', 'submitApproval', 'cancelRemaining'];
    $action = in_array($action, $allowedActions, true) ? $action : null;
@endphp
@if($action)
    <x-v65.button :variant="$variant" :disabled="$dirty" :title="$dirty ? 'Önce kaydedilmemiş değişiklikleri kaydedin.' : null" data-v65-modal-open="{{ $id }}">{{ $label }}</x-v65.button>
    <x-v65.modal :id="$id" :title="$title" size="sm">
        <p>{{ $message }}</p>
        @error('document')
            <p class="v65-doc-confirm-error" role="alert">{{ $message }}</p>
        @enderror
        @if($document)
            <dl class="v65-doc-confirm-summary">
                <div><dt>Belge</dt><dd>{{ $document->number ?? 'Taslak' }}</dd></div>
                <div><dt>Cari / Tedarikçi</dt><dd>{{ $document->contact?->title ?? '—' }}</dd></div>
                <div><dt>Son kaydedilen toplam</dt><dd>{{ \App\Support\Formatting\TrFormatter::money((string) $document->grand_total) }} {{ $document->currency }}</dd></div>
            </dl>
        @endif
        <x-slot:footer>
            <x-v65.button variant="secondary" data-v65-modal-close>Vazgeç</x-v65.button>
            <x-v65.button :variant="$variant" wire:click="{{ $action }}" wire:loading.attr="disabled" wire:target="{{ $action }}">{{ $label }}</x-v65.button>
        </x-slot:footer>
    </x-v65.modal>
@endif
