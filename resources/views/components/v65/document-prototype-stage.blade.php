@props(['document' => null, 'screenType'])
@php
    $screen = config('document_prototype.'.$screenType, []);
    $stages = $screen['stages'] ?? ['Taslak'];
    $status = $document?->status ?? 'draft';
    // Visual process indicator only; actual state is always the persisted status.
    $current = match ($status) {
        'approved', 'confirmed', 'sent', 'posted' => 2,
        'internal_review', 'customer_review', 'pending_approval' => 1,
        default => 0,
    };
@endphp
<div {{ $attributes->class(['v65-reference-stage']) }}>
    <span class="v65-reference-stage-number"><strong>Belge No:</strong> {{ $document?->number ?: 'Kaydedildiğinde oluşturulur' }}</span>
    <div class="v65-reference-stage-steps" aria-label="Belge süreç adımları">
        @foreach($stages as $index => $label)
            <span @class(['is-current' => $current === $index, 'is-complete' => $current > $index])>
                {{ $label }}
            </span>
        @endforeach
    </div>
</div>
