@props(['document' => null, 'title' => 'Belge', 'section' => 'Belge Yönetimi', 'indexRoute' => null])
@php
    $statusNames = [
        'draft' => 'Taslak', 'internal_review' => 'İç İnceleme',
        'customer_review' => 'Müşteri İncelemesi', 'pending_approval' => 'Onay Bekliyor',
        'approved' => 'Onaylı', 'sent' => 'Gönderildi', 'confirmed' => 'Onaylandı',
        'posted' => 'Kesinleşti', 'cancelled' => 'İptal', 'reversed' => 'Ters Kayıt',
    ];
    $state = $document?->status ?? 'draft';
    $tone = match ($state) {
        'approved', 'confirmed', 'posted' => 'success',
        'cancelled', 'reversed' => 'danger',
        'pending_approval', 'internal_review', 'customer_review' => 'warning',
        'sent' => 'info',
        default => 'neutral',
    };
@endphp
<header {{ $attributes->class(['v65-doc-heading']) }}>
    <div class="v65-doc-heading-copy">
        <span class="v65-doc-caption">{{ $section }}</span>
        <div class="v65-doc-identification">
            <strong>{{ $document?->number ?: 'Yeni Belge' }}</strong>
            <x-v65.status-badge :tone="$tone" :dot="true">{{ $statusNames[$state] ?? $state }}</x-v65.status-badge>
        </div>
        <small>{{ $title }}@if($document && $document->revision_no) · Revizyon {{ $document->revision_no }}@endif</small>
    </div>
    <div class="v65-doc-heading-actions">
        @if($document)
            <x-v65.button variant="secondary" size="sm" onclick="window.print()">Yazdır / PDF</x-v65.button>
        @endif
        @if($indexRoute)
            <x-v65.button variant="ghost" size="sm" :href="route($indexRoute)">← Listeye Dön</x-v65.button>
        @endif
    </div>
</header>
