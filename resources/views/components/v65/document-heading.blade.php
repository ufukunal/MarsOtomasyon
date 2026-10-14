@props(['document' => null, 'title' => 'Belge', 'section' => 'Belge Yönetimi'])
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
    @if($document && $document->status !== 'draft')
        <small class="v65-doc-readonly-hint">Bu aşamada belge satırları salt okunur gösterilir.</small>
    @endif
</header>
