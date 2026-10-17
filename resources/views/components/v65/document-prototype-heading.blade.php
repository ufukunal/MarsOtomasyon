@props(['document' => null, 'screenType', 'indexRoute' => null, 'formId' => null, 'dirty' => false])

@php
    $screen = config('document_prototype.'.$screenType, []);
    $statusNames = [
        'draft' => 'Taslak', 'internal_review' => 'İç İnceleme',
        'customer_review' => 'Müşteri İncelemesi', 'pending_approval' => 'Onay Bekliyor',
        'approved' => 'Onaylı', 'confirmed' => 'Onaylandı', 'sent' => 'Gönderildi',
        'posted' => 'Kesinleşti', 'cancelled' => 'İptal', 'reversed' => 'Ters Kayıt',
    ];
    $currentState = $document?->status ?? 'draft';
    $editable = $document === null || $currentState === 'draft';
    $saveLabel = $screen['save'] ?? 'Belgeyi Kaydet';
@endphp

<header {{ $attributes->class(['v65-reference-heading']) }}>
    <div class="v65-reference-heading-main">
        <h1>{{ $screen['title'] ?? 'Belge' }}</h1>
        <p>
            {{ $screen['description'] ?? '' }}
            @if($document?->number)
                <span class="v65-reference-heading-number">{{ $document->number }}</span>
            @endif
        </p>
        @if($dirty)
            <small class="v65-reference-unsaved">Kaydedilmemiş değişiklikler mevcut; çıktı son kaydedilen belgeyi kullanır.</small>
        @endif
    </div>
    <div class="v65-reference-heading-actions">
        <span class="v65-reference-status"><i aria-hidden="true"></i>{{ $statusNames[$currentState] ?? $currentState }}</span>
        @if($document)
            <x-v65.button variant="secondary" size="sm"
                :href="route('documents.print', ['document' => $document->id, 'format' => 'html'])"
                target="_blank" rel="noopener noreferrer">Yazdır</x-v65.button>
        @else
            <span class="v65-reference-print-placeholder" title="Yazdırma, ilk kayıt sonrasında kullanılabilir">Yazdır: kayıt sonrası</span>
        @endif
        @if($editable && $formId)
            <x-v65.button type="submit" size="sm" :form="$formId"
                wire:loading.attr="disabled" wire:target="save">{{ $saveLabel }}</x-v65.button>
        @endif
        @if($indexRoute)
            <x-v65.button variant="ghost" size="sm" :href="route($indexRoute)">Listeye Dön</x-v65.button>
        @endif
    </div>
</header>
